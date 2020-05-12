<?php

require_once dirname(__FILE__) . '/vendor/autoload.php';
require_once dirname(__FILE__) . '/database.php';

use Slim\App;
use Slim\Http\Stream;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;

$config = [
    'displayErrorDetails' => true,
    'addContentLengthHeader' => false,
    'db' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => 'root',
        'dbname' => 'agenda-slim',
    ]
];

$app = new App([
    'settings' => $config
]);

$container = $app->getContainer();

$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'], $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

/**
 * Enable CORS
 */
$app->add(function ($request, $response, $next) {
    $response = $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', '*')
        ->withHeader("Access-Control-Allow-Methods", '*');

    if ($request->isOptions() && $request->hasHeader('Origin') && $request->hasHeader('Access-Control-Request-Method')) {
        return $response;
    }

    return $next($request, $response);
});

/*
 * Return React app
 */
$app->get('/', function (Request $request, Response $response) {

});

/**
 * API Routes
 */
$app->group('/api', function (App $app) {

    $app->get('/download/{uuid}', function (Request $request, Response $response, $args) {
        if (empty($args['uuid'])) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Você deve informar o UUID'
            ], 400);
        }

        $file = getAppointmentFile($this->db, $args['uuid']);

        if (!$file) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Arquivo não encontrado'
            ], 404);
        }

        if (!file_exists($file['path'])) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Arquivo não encontrado no disco'
            ], 404);
        }

        $fh = fopen($file['path'], 'rb');
        $stream = new Stream($fh);

        return $response->withHeader('Content-Type', 'application/force-download')
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Type', 'application/download')
            ->withHeader('Content-Description', 'File Transfer')
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($file['name']) . '"')
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->withHeader('Pragma', 'public')
            ->withBody($stream);
    });

    $app->get('/appointments', function (Request $request, Response $response) {
        // TODO(Pedro): handle this
        $appointments = getAppointments($this->db);
        return $response->withJson($appointments);
    });

    $app->get('/appointments/{appointment}', function (Request $request, Response $response, $args) {
        if (empty($args['appointment'])) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Você deve informar o ID'
            ], 400);
        }

        $appointment = getAppointment($this->db, $args['appointment']);

        if (!$appointment) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Agendamento não encontrado'
            ], 404);
        }

        return $response->withJson($appointment);
    });

    $app->post('/appointments', function (Request $request, Response $response) {
        $contents = $request->getParsedBody();


        // todo(Pedro): store the appointment
    });
})->add(function ($request, $response, $next) {
    // TODO(Pedro): allow only auth users
    return $next($request, $response);
});

$app->run();
