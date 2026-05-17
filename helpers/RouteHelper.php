<?php
/**
 * RTTC 2026 - Route Helper
 * Provides the route() function used throughout views.
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

/**
 * Generate a URL for a named route.
 * Strips trailing slash from APP_URL automatically.
 */
function route(string $name, array $params = []): string
{
    $base = rtrim(APP_URL, '/');

    $map = [
        // Public
        'home'                => '/',
        'login'               => '/login',
        'signup'              => '/signup',
        'logout'              => '/logout',
        'forgot-password'     => '/forgot-password',
        'reset-password'      => '/reset-password',

        // Registration flow
        'welcome'             => '/welcome',
        'registration'        => '/registration',
        'academics'           => '/academics',
        'documents'           => '/documents',

        // Payment
        'payment'             => '/payment',
        'payment.process'     => '/api/payment-process',
        'payment.confirmation'=> '/payment/confirmation',
        'payment.webhook'     => '/api/payment-webhook',

        // API / AJAX
        'api.send-otp'        => '/api/send-otp',
        'api.verify-otp'      => '/api/verify-otp',
        'api.check-email'     => '/api/check-email',
        'api.check-phone'     => '/api/check-phone',

        // Admin
        'admin.login'             => '/admin/login',
        'admin.logout'            => '/admin/logout',
        'admin.dashboard'         => '/admin/dashboard',
        'admin.students'          => '/admin/students',
        'admin.students.index'    => '/admin/students',
        'admin.students.view'     => '/admin/students/view',
        'admin.student.view'      => '/admin/students/view', // backward compatibility
        'admin.applications'      => '/admin/applications',
        'admin.applications.index'=> '/admin/applications',
        'admin.payments'          => '/admin/payments',
        'admin.payments.index'    => '/admin/payments',
        'admin.export'            => '/admin/export',
        'admin.settings'          => '/admin/settings',
        'admin.notice-documents'  => '/admin/notice-documents',
        'admin.marquee'           => '/admin/marquee',

        // Errors
        'error.404'               => '/error/404',
        'error.403'               => '/error/403',
        'error.500'               => '/error/500',
    ];

    $path = $map[$name] ?? "/$name";

    // Append query params
    if (!empty($params)) {
        $path .= '?' . http_build_query($params);
    }

    return $base . $path;
}

/**
 * Redirect helper. Accepts either:
 *  - A full URL (starts with http or /)
 *  - A route name (will be resolved via route())
 */
function redirect(string $urlOrRoute, array $params = [], string $flashType = '', string $flashMsg = ''): never
{
    if ($flashType && $flashMsg) {
        SessionHelper::setFlash($flashType, $flashMsg);
    }
    // If it looks like a full URL or absolute path, use directly
    if (str_starts_with($urlOrRoute, 'http') || str_starts_with($urlOrRoute, '/')) {
        $url = $urlOrRoute;
        if (!empty($params)) $url .= '?' . http_build_query($params);
    } else {
        $url = route($urlOrRoute, $params);
    }
    header('Location: ' . $url);
    exit;
}
