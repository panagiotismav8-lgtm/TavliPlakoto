<?php
/**
 * JSON Response Helper
 */

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data);
    exit;
}

function successResponse($data, $message = 'Success') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

function errorResponse($message, $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'message' => $message,
        'data' => null
    ], $statusCode);
}
?>
