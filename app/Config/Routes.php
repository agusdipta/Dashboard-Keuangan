<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::authenticate');
$routes->get('register', 'AuthController::register');
$routes->post('register', 'AuthController::store');

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('/', 'DashboardController::index');
    $routes->post('logout', 'AuthController::logout');
    $routes->post('transactions', 'TransactionsController::store');
    $routes->post('transactions/(:num)/delete', 'TransactionsController::delete/$1');
    $routes->post('categories', 'CategoriesController::store');
    $routes->post('targets', 'TargetsController::save');
});
