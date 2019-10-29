<?php namespace Tancredi;

include_once(__DIR__ . '/../src/init.php');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app = new \Slim\App;

/*********************************
* GET /phones
**********************************/
$app->get('/phones', function(Request $request, Response $response) use ($app) {
    global $log;
    $log->debug("GET /phones/");
    $scopes = listScopes('phone');
    $results = array();
    foreach ($scopes as $scopeId) {
        $scope = new \Tancredi\Entity\Scope($scopeId);
        $scope_data = $scope->getVariables();
        $results[] = array(
            'mac' => $scopeId,
            'model' => $scope->metadata['inheritFrom'],
            'display_name' => $scope->metadata['displayName'],
            'model_url' => "/tancredi/api/v1/models/" . $scope->metadata['inheritFrom'],
            'phone_url' => "/tancredi/api/v1/models/" . $scopeId
        );
    }
    return $response->withJson($results,200);
});

/*********************************
* GET /phones/{mac}
**********************************/
$app->get('/phones/{mac}', function(Request $request, Response $response, array $args) use ($app) {
    global $log;
    $mac = $args['mac'];
    $log->debug("GET /phones/" . $mac);
    // get all scopes of type "phone"
    if (!scopeExists($mac)) {
        $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#not-found',
            'title' => 'Resource not found'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,404);
    }
    return $response->withJson(getPhoneScope($mac),200);
});

/*********************************
* POST /phones
**********************************/
$app->post('/phones', function (Request $request, Response $response, $args) {
    global $log;
    $post_data = $request->getParsedBody();
    $log->debug("POST /phones " . json_encode($post_data));
    $mac = $post_data['mac'];
    $model = $post_data['model'];
    $display_name = ($post_data['display_name'] ? $post_data['display_name'] : "" );
    $variables = $post_data['variables'];
    if (scopeExists($mac)) {
        // Error: scope is already configured
        $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#phone-exists',
            'title' => 'The phone mac address is already registered'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,409);
    }
    $scope = new \Tancredi\Entity\Scope($mac);
    $scope->metadata['displayName'] = $display_name;
    $scope->metadata['inheritFrom'] = $model;
    $scope->metadata['model'] = $model;
    $scope->metadata['scopeType'] = "phone";
    $scope->setVariables($variables);
    \Tancredi\Entity\TokenManager::createToken(uniqid($prefix = rand(), $more_entropy = TRUE), $mac , TRUE); // create first time access token
    \Tancredi\Entity\TokenManager::createToken(uniqid($prefix = rand(), $more_entropy = TRUE), $mac , FALSE); // create token
    return $response->withJson(getPhoneScope($mac),201);
});

/*********************************
* PATCH /phones/{mac}
**********************************/
$app->patch('/phones/{mac}', function (Request $request, Response $response, $args) {
    global $log;
    $mac = $args['mac'];
    $patch_data = $request->getParsedBody();
    $log->debug("PATCH /phones/" .$mac . " " . json_encode($patch_data));

    if (!scopeExists($mac)) {
        $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#not-found',
            'title' => 'Resource not found'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,404);
    }

    if (array_key_exists('mac',$patch_data) or array_key_exists('model_url',$patch_data) or array_key_exists('tok1',$patch_data) or array_key_exists('tok2',$patch_data)) {
        $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#read-only-attribute',
            'title' => 'Cannot change a read-only attribute'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,403);
    }

    if (array_key_exists('model',$patch_data)) {
        $scope = new \Tancredi\Entity\Scope($mac);
        $scope->metadata['inheritFrom'] = $patch_data['model'];
        $scope->metadata['model'] = $patch_data['model'];
        $scope->setVariables();
        return $response->withJson(getPhoneScope($mac),200);
    }
    if (array_key_exists('variables',$patch_data)) {
        $scope = new \Tancredi\Entity\Scope($mac);
        $scope->setVariables($patch_data['variables']);
        return $response->withStatus(204);
    }
    return $response->withStatus(400);
});

/*********************************
* DELETE /phones/{mac}
**********************************/
$app->delete('/phones/{mac}', function (Request $request, Response $response, $args) {
    global $log;
    $mac = $args['mac'];
    $log->debug("DELETE /phones/" .$mac);

    if (!scopeExists($mac)) {
        $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#not-found',
            'title' => 'Resource not found'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,404);
    }
    \Tancredi\Entity\TokenManager::deleteTok1ForId($mac);
    \Tancredi\Entity\TokenManager::deleteTok2ForId($mac);
    deleteScope($mac);
    return $response->withStatus(204);
});

/*********************************
* GET /models
**********************************/
$app->get('/models', function(Request $request, Response $response) use ($app) {
    global $log;
    $log->debug("GET /models/");
    $scopes = listScopes('model');
    $results = array();
    foreach ($scopes as $scopeId) {
        $scope = new \Tancredi\Entity\Scope($scopeId);
        $scope_data = $scope->getVariables();
        $results[] = array(
            'name' => $scopeId,
            'display_name' => $scope->metadata['displayName'],
            'model_url' => "/tancredi/api/v1/models/" . $scopeId
        );
    }
    return $response->withJson($results,200);
});

/*********************************
* GET /models/{id}
**********************************/
$app->get('/models/{id}', function(Request $request, Response $response, array $args) use ($app) {
    global $log;
    $id = $args['id'];
    $query = $request->getQueryParams();
    $log->debug("GET /models/" . $id . " " . json_encode($query));
    // get all scopes of type "model"
    if (!scopeExists($id)) {
        $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#not-found',
            'title' => 'Resource not found'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,404);
    }
    if (array_key_exists('inherit',$query) and $query['inherit'] == 1) {
        $results = getModelScope($id, true);
    } else {
        $results = getModelScope($id, false);
    }
    return $response->withJson($results,200);
});

/*********************************
* POST /models
**********************************/
$app->post('/models', function (Request $request, Response $response, $args) {
    global $log;
    $post_data = $request->getParsedBody();
    $log->debug("POST /models " . json_encode($post_data));
    $id = $post_data['name'];
    $display_name = ($post_data['display_name'] ? $post_data['display_name'] : "" );
    $variables = $post_data['variables'];
    if (scopeExists($id)) {
        // Error: scope is already configured
        $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#phone-exists',
            'title' => 'The model name is already registered'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,409);
    }
    $scope = new \Tancredi\Entity\Scope($id);
    $scope->metadata['displayName'] = $display_name;
    $scope->metadata['inheritFrom'] = 'globals';
    $scope->metadata['scopeType'] = "model";
    $scope->setVariables($variables);
    return $response->withJson(getPhoneScope($mac),201);
});

/*********************************
* PATCH /models/{id}
**********************************/
$app->patch('/models/{id}', function (Request $request, Response $response, $args) {
    global $log;
    $mac = $args['id'];
    $patch_data = $request->getParsedBody();
    $log->debug("PATCH /models/" .$id . " " . json_encode($patch_data));

    if (!scopeExists($id)) {
        $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#not-found',
            'title' => 'Resource not found'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,404);
    }

    if (array_key_exists('name',$patch_data)) {
        $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#read-only-attribute',
            'title' => 'Cannot change a read-only attribute'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,403);
    }

    if (array_key_exists('variables',$patch_data) or array_key_exists('display_name',$patch_data)) {
        $scope = new \Tancredi\Entity\Scope($id);
        if (array_key_exists('display_name',$patch_data)) {
            $scope->metadata['displayName'] = $patch_data['display_name'];
        }
        if (array_key_exists('variables',$patch_data)) {
             $scope->setVariables($patch_data['variables']);
        } else {
             $scope->setVariables();
        }
        return $response->withStatus(204);
    }
    return $response->withStatus(400);
});

/*********************************
* DELETE /models/{id}
**********************************/
$app->delete('/models/{id}', function (Request $request, Response $response, $args) {
    global $log;
    $mac = $args['id'];
    $log->debug("DELETE /models/" .$id);

    if (!scopeExists($id)) {
        $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#not-found',
            'title' => 'Resource not found'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,404);
    }

    if (scopeInUse($id)) {
         $results = array(
            'type' => 'https://github.com/nethesis/tancredi/wiki/problems#resource-in-use',
            'title' => 'The resource is in use by other resources and cannot be deleted'
        );
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response->withJson($results,409);
    }

    deleteScope($id);
    return $response->withStatus(204);
});

/*********************************
* GET /defaults
**********************************/
$app->get('/defaults', function(Request $request, Response $response) use ($app) {
    global $log;
    $log->debug("GET /defaults");
    $scope = new \Tancredi\Entity\Scope('globals');
    $scope_data = $scope->getVariables();
    return $response->withJson($scope_data,200);
});

/*********************************
* PATCH /defaults
**********************************/
$app->patch('/defaults', function (Request $request, Response $response, $args) {
    global $log;
    $patch_data = $request->getParsedBody();
    $log->debug("PATCH /defaults" . json_encode($patch_data));

    $scope = new \Tancredi\Entity\Scope('globals');
    foreach ($patch_data as $patch_key => $patch_value) {
        if (is_null($patch_value)) {
            unset($scope->data[$patch_key]);
            unset($patch_data[$patch_key]);
        }
    }

    $scope->setVariables($patch_data);

    return $response->withStatus(204);
});




function getModelScope($id,$inherit = false) {
    $scope = new \Tancredi\Entity\Scope($id);
    if ($inherit) {
        $scope_data = $scope->getVariables();
    } else {
        $scope_data = $scope->data;
    }
    $results = array(
        'name' => $id,
        'display_name' => $scope->metadata['displayName'],
        'variables' => $scope_data,
        'model_url' => "/tancredi/api/v1/models/" . $scope->metadata['inheritFrom']
    );
    return $results;
}


function getPhoneScope($mac,$inherit = false) {
    $scope = new \Tancredi\Entity\Scope($mac);
    if ($inherit) {
        $scope_data = $scope->getVariables();
    } else {
        $scope_data = $scope->data;
    }
    $results = array(
        'mac' => $mac,
        'model' => $scope->metadata['inheritFrom'],
        'display_name' => $scope->metadata['displayName'],
        'tok1' => \Tancredi\Entity\TokenManager::getToken1($mac),
        'tok2' => \Tancredi\Entity\TokenManager::getToken2($mac),
        'variables' => $scope_data,
        'model_url' => "/tancredi/api/v1/models/" . $scope->metadata['inheritFrom']
    );
    return $results;
}

// Run app
$app->run();
