<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Definición de Rutas MVC · App-Transcripciones
 *
 * @var RouteCollection $routes
 */

// 1. Rutas públicas de Autenticación y Acceso Principal
$routes->get('/', 'MeetingController::index');
$routes->get('login', 'AuthController::loginView');
$routes->post('auth/login', 'AuthController::login');
$routes->post('auth/register', 'AuthController::register');
$routes->post('auth/google', 'AuthController::google');
$routes->post('auth/logout', 'AuthController::logout');

// 2. Rutas protegidas por AuthFilter (Sesión CI4 o Token JWT Bearer / Cookie)
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes) {
    // API de Reuniones e Ingesta de archivos .txt
    $routes->get('api/meetings', 'MeetingController::list');
    $routes->post('api/meetings/upload', 'MeetingController::upload');
    $routes->get('api/meetings/(:num)', 'MeetingController::show/$1');
    $routes->patch('api/meetings/(:num)', 'MeetingController::update/$1');
    $routes->post('api/meetings/(:num)', 'MeetingController::update/$1');
    $routes->get('api/meetings/(:num)/export', 'MeetingController::export/$1');
    $routes->delete('api/meetings/(:num)', 'MeetingController::delete/$1');

    // Módulo 1: API del Gestor de Minutas y Tareas
    $routes->post('api/tasks', 'TaskController::create');
    $routes->patch('api/tasks/(:num)', 'TaskController::update/$1');
    $routes->post('api/tasks/(:num)', 'TaskController::update/$1');
    $routes->delete('api/tasks/(:num)', 'TaskController::delete/$1');

    // Módulo 2: API del Buscador Semántico de Decisiones Técnicas
    $routes->get('api/decisions/search', 'SearchController::decisions');
    $routes->post('api/decisions', 'SearchController::create');
    $routes->delete('api/decisions/(:num)', 'SearchController::delete/$1');
});
