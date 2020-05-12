<?php

require_once dirname(__FILE__) . '/vendor/autoload.php';
require_once dirname(__FILE__) . '/database.php';

use Slim\App;
use Slim\Http\Stream;
use Mimey\MimeTypes;
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

function getBaseURL()
{
    return 'http://agenda-slim.test';
}

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

$container['notFoundHandler'] = function ($c) {
    return function (Request $request, Response $response) use ($c) {
        $file = $request->getUri()->getPath();
        $file = str_ireplace('../', '/', $file);

        $newPath = './frontend/build/' . $file;

        if (file_exists($newPath)) {
            $mimes = new MimeTypes;
            $ext = pathinfo($newPath, PATHINFO_EXTENSION);

            return $response->withStatus(200)
                ->withHeader('Content-Type', $mimes->getMimeType($ext))
                ->write(file_get_contents($newPath));
        }

        return $response->withStatus(200)
            ->withHeader('Content-Type', 'text/html')
            ->write(file_get_contents('./frontend/build/index.html'));
    };
};

@mkdir('./api/download', 0777, true);

/**
 * Migrate if needed
 */
$app->add(function ($request, $response, $next) {
    setupDatabase($this->db);
    return $next($request, $response);
});

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
    return $response->withStatus(200)
        ->withHeader('Content-Type', 'text/html')
        ->write(file_get_contents('./frontend/build/index.html'));
});

/**
 * Poor's man implementation of a redirect
 */

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
        $userID = getUserID();
        $date = isset($_GET['date']) ? $_GET['date'] : null;
        $returnDate = isset($_GET['return_date']) ? $_GET['return_date'] : null;
        $dueDate = isset($_GET['due_date']) ? $_GET['due_date'] : null;

        $appointments = getAppointments($this->db, $userID, $date, $returnDate, $dueDate);
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
        $userID = getUserID();
        $name = !empty($contents['name']) ? $contents['name'] : null;
        $address = !empty($contents['address']) ? $contents['address'] : null;
        $landlinePhoneNumber = !empty($contents['landline_phone_number']) ? $contents['landline_phone_number'] : null;
        $mobilePhoneNumber = !empty($contents['mobile_phone_number']) ? $contents['mobile_phone_number'] : null;
        $email = !empty($contents['email']) ? $contents['email'] : null;
        $numberOfEmployees = !empty($contents['number_of_employees']) ? $contents['number_of_employees'] : null;
        $date = !empty($contents['date']) ? $contents['date'] : null;
        $returnDate = !empty($contents['return_date']) ? $contents['return_date'] : null;
        $dueDate = !empty($contents['due_date']) ? $contents['due_date'] : null;
        $observations = !empty($contents['observations']) ? $contents['observations'] : null;
        $documents = !empty($contents['documents']) ? $contents['documents'] : [];

        if (!$name) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Você deve informar o nome'
            ], 400);
        }

        if (!$email) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Você deve informar o email'
            ], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Você deve informar um email válido'
            ], 400);
        }

        if (!$mobilePhoneNumber) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Você deve informar o telefone móvel'
            ], 400);
        }

        $id = saveAppointment($this->db,
            $userID,
            $name,
            $address,
            $landlinePhoneNumber,
            $mobilePhoneNumber,
            $email,
            $numberOfEmployees,
            $date,
            $returnDate,
            $dueDate,
            $observations
        );

        if (!$id) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Não foi possível cadastrar o agendamento'
            ], 500);
        }

        foreach ($documents as $file) {
            $path = './api/download/' . time() . "_" . $file['name'];
            file_put_contents($path, base64_decode($file['content']));
            saveAppointmentFile($this->db, $id, $file['name'], $path);
        }

        $appointment = getAppointment($this->db, $id);

        if (!$appointment) {
            return $response->withJson([
                'erro' => true,
                'message' => 'Agendamento não encontrado'
            ], 404);
        }

        return $response->withJson($appointment);
    });
})->add(function ($request, $response, $next) {

    if (getUserID()) {
        return $next($request, $response);
    }

    return $response->withJson([
        'erro' => true,
        'message' => 'Você deve fazer login para poder ver essa página'
    ], 401);
});

$app->run();
