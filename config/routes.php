<?php

declare(strict_types=1);

/**
 * Route definitions for NanoPub.
 * 
 * Format: 'METHOD /path' => [ControllerClass::class, 'methodName']
 * Or: 'METHOD /path' => callable
 * 
 * Route parameters: '/users/{id}' matches '/users/123' and passes '123' as $id
 */

// Base web routes
$routes = [
    // ============================================
    // Web Routes
    // ============================================
    'GET' => [
        // Home
        '/' => [\NanoPub\Controllers\Web\HomeController::class, 'index'],
        '/about' => [\NanoPub\Controllers\Web\HomeController::class, 'about'],
        '/public' => [\NanoPub\Controllers\Web\HomeController::class, 'publicTimeline'],
        
        // Authentication
        '/login' => [\NanoPub\Controllers\Web\AuthController::class, 'showLogin'],
        '/register' => [\NanoPub\Controllers\Web\AuthController::class, 'showRegister'],
        '/logout' => [\NanoPub\Controllers\Web\AuthController::class, 'logout'],
        
        // Profiles
        '/@{username}' => [\NanoPub\Controllers\Web\ProfileController::class, 'show'],
        '/@{username}/statuses/{id}' => [\NanoPub\Controllers\Web\StatusController::class, 'show'],
        '/@{username}/followers' => [\NanoPub\Controllers\Web\ProfileController::class, 'followers'],
        '/@{username}/following' => [\NanoPub\Controllers\Web\ProfileController::class, 'following'],
        
        // Notifications & Bookmarks
        '/notifications' => [\NanoPub\Controllers\Web\NotificationController::class, 'index'],
        '/bookmarks' => [\NanoPub\Controllers\Web\BookmarkController::class, 'index'],
        '/explore' => [\NanoPub\Controllers\Web\ExploreController::class, 'index'],
        
        // Settings
        '/settings' => [\NanoPub\Controllers\Web\SettingsController::class, 'index'],
        '/settings/profile' => [\NanoPub\Controllers\Web\SettingsController::class, 'profile'],
        '/settings/account' => [\NanoPub\Controllers\Web\SettingsController::class, 'account'],
        '/settings/privacy' => [\NanoPub\Controllers\Web\SettingsController::class, 'privacy'],
        '/settings/notifications' => [\NanoPub\Controllers\Web\SettingsController::class, 'notifications'],
        
        // Admin
        '/admin' => [\NanoPub\Controllers\Web\AdminController::class, 'index'],
        '/admin/users' => [\NanoPub\Controllers\Web\AdminController::class, 'users'],
        '/admin/instances' => [\NanoPub\Controllers\Web\AdminController::class, 'instances'],
        '/admin/reports' => [\NanoPub\Controllers\Web\AdminController::class, 'reports'],
        '/admin/settings' => [\NanoPub\Controllers\Web\AdminController::class, 'settings'],
    ],
    
    'POST' => [
        // Authentication
        '/login' => [\NanoPub\Controllers\Web\AuthController::class, 'login'],
        '/register' => [\NanoPub\Controllers\Web\AuthController::class, 'register'],
        
        // Statuses
        '/statuses' => [\NanoPub\Controllers\Web\StatusController::class, 'create'],
        '/statuses/{id}/favourite' => [\NanoPub\Controllers\Web\StatusController::class, 'favourite'],
        '/statuses/{id}/unfavourite' => [\NanoPub\Controllers\Web\StatusController::class, 'unfavourite'],
        '/statuses/{id}/reblog' => [\NanoPub\Controllers\Web\StatusController::class, 'reblog'],
        '/statuses/{id}/unreblog' => [\NanoPub\Controllers\Web\StatusController::class, 'unreblog'],
        
        // Profiles
        '/@{username}/follow' => [\NanoPub\Controllers\Web\ProfileController::class, 'follow'],
        '/@{username}/unfollow' => [\NanoPub\Controllers\Web\ProfileController::class, 'unfollow'],
        
        // Settings
        '/settings/profile' => [\NanoPub\Controllers\Web\SettingsController::class, 'updateProfile'],
        '/settings/account' => [\NanoPub\Controllers\Web\SettingsController::class, 'updateAccount'],
        
        // Admin
        '/admin/users/{id}/action' => [\NanoPub\Controllers\Web\AdminController::class, 'userAction'],
        '/admin/instances/{id}/action' => [\NanoPub\Controllers\Web\AdminController::class, 'instanceAction'],
    ],
    
    'PUT' => [
        // Settings
        '/settings/profile' => [\NanoPub\Controllers\Web\SettingsController::class, 'updateProfile'],
        '/settings/account' => [\NanoPub\Controllers\Web\SettingsController::class, 'updateAccount'],
    ],
    
    'DELETE' => [
        // Statuses
        '/statuses/{id}' => [\NanoPub\Controllers\Web\StatusController::class, 'delete'],
    ],
    
    'PATCH' => [
        // Settings
        '/settings/privacy' => [\NanoPub\Controllers\Web\SettingsController::class, 'updatePrivacy'],
        '/settings/notifications' => [\NanoPub\Controllers\Web\SettingsController::class, 'updateNotifications'],
    ],
];

// ============================================
// API v1 Routes (Mastodon-compatible)
// ============================================
$apiV1Routes = [
    'GET' => [
        '/api/v1/accounts/verify_credentials' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'verifyCredentials'],
        '/api/v1/accounts/{id}' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'show'],
        '/api/v1/accounts/{id}/statuses' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'statuses'],
        '/api/v1/accounts/{id}/followers' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'followers'],
        '/api/v1/accounts/{id}/following' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'following'],
        
        '/api/v1/statuses/{id}' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'show'],
        '/api/v1/statuses/{id}/context' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'context'],
        '/api/v1/statuses/{id}/favourited_by' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'favouritedBy'],
        '/api/v1/statuses/{id}/reblogged_by' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'rebloggedBy'],
        
        '/api/v1/timelines/home' => [\NanoPub\Controllers\Api\v1\TimelinesController::class, 'home'],
        '/api/v1/timelines/public' => [\NanoPub\Controllers\Api\v1\TimelinesController::class, 'public'],
        '/api/v1/timelines/tag/{hashtag}' => [\NanoPub\Controllers\Api\v1\TimelinesController::class, 'hashtag'],
        
        '/api/v1/notifications' => [\NanoPub\Controllers\Api\v1\NotificationsController::class, 'index'],
        '/api/v1/notifications/{id}' => [\NanoPub\Controllers\Api\v1\NotificationsController::class, 'show'],
        
        '/api/v1/instance' => [\NanoPub\Controllers\Api\v1\InstanceController::class, 'show'],
        '/api/v1/instance/peers' => [\NanoPub\Controllers\Api\v1\InstanceController::class, 'peers'],
        '/api/v1/instance/activity' => [\NanoPub\Controllers\Api\v1\InstanceController::class, 'activity'],
        
        '/api/v1/custom_emojis' => [\NanoPub\Controllers\Api\v1\EmojiController::class, 'index'],
        
        '/api/v1/search' => [\NanoPub\Controllers\Api\v1\SearchController::class, 'index'],
    ],
    
    'POST' => [
        '/api/v1/accounts/{id}/follow' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'follow'],
        '/api/v1/accounts/{id}/unfollow' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'unfollow'],
        '/api/v1/accounts/{id}/block' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'block'],
        '/api/v1/accounts/{id}/unblock' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'unblock'],
        '/api/v1/accounts/{id}/mute' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'mute'],
        '/api/v1/accounts/{id}/unmute' => [\NanoPub\Controllers\Api\v1\AccountsController::class, 'unmute'],
        
        '/api/v1/statuses' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'create'],
        '/api/v1/statuses/{id}/favourite' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'favourite'],
        '/api/v1/statuses/{id}/unfavourite' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'unfavourite'],
        '/api/v1/statuses/{id}/reblog' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'reblog'],
        '/api/v1/statuses/{id}/unreblog' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'unreblog'],
        '/api/v1/statuses/{id}/bookmark' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'bookmark'],
        '/api/v1/statuses/{id}/unbookmark' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'unbookmark'],
        
        '/api/v1/media' => [\NanoPub\Controllers\Api\v1\MediaController::class, 'create'],
        
        '/api/v1/notifications/clear' => [\NanoPub\Controllers\Api\v1\NotificationsController::class, 'clear'],
        '/api/v1/notifications/{id}/dismiss' => [\NanoPub\Controllers\Api\v1\NotificationsController::class, 'dismiss'],
        
        '/api/v1/apps' => [\NanoPub\Controllers\Api\v1\AppController::class, 'create'],

        // OAuth
        '/oauth/token' => [\NanoPub\Controllers\Api\v1\AuthController::class, 'token'],
        '/oauth/revoke' => [\NanoPub\Controllers\Api\v1\AuthController::class, 'revoke'],
    ],
    
    'PUT' => [
        '/api/v1/media/{id}' => [\NanoPub\Controllers\Api\v1\MediaController::class, 'update'],
    ],
    
    'DELETE' => [
        '/api/v1/statuses/{id}' => [\NanoPub\Controllers\Api\v1\StatusesController::class, 'delete'],
    ],
];

// ============================================
// API v2 Routes (Mastodon-compatible)
// ============================================
$apiV2Routes = [
    'GET' => [
        '/api/v2/instance' => [\NanoPub\Controllers\Api\v2\InstanceController::class, 'show'],
        '/api/v2/search' => [\NanoPub\Controllers\Api\v2\SearchController::class, 'index'],
    ],
];

// ============================================
// ActivityPub Routes
// ============================================
$activityPubRoutes = [
    'GET' => [
        '/.well-known/webfinger' => [\NanoPub\Controllers\ActivityPub\WebfingerController::class, 'show'],
        '/.well-known/nodeinfo' => [\NanoPub\Controllers\ActivityPub\NodeInfoController::class, 'index'],
        '/.well-known/host-meta' => [\NanoPub\Controllers\ActivityPub\HostMetaController::class, 'show'],
        
        '/nodeinfo/2.0' => [\NanoPub\Controllers\ActivityPub\NodeInfoController::class, 'show'],
        
        '/users/{username}' => [\NanoPub\Controllers\ActivityPub\ActorController::class, 'show'],
        '/users/{username}/outbox' => [\NanoPub\Controllers\ActivityPub\OutboxController::class, 'index'],
        '/users/{username}/followers' => [\NanoPub\Controllers\ActivityPub\FollowersController::class, 'index'],
        '/users/{username}/following' => [\NanoPub\Controllers\ActivityPub\FollowingController::class, 'index'],
    ],
    
    'POST' => [
        '/users/{username}/inbox' => [\NanoPub\Controllers\ActivityPub\InboxController::class, 'user'],
        '/inbox' => [\NanoPub\Controllers\ActivityPub\InboxController::class, 'shared'],
    ],
];

// Merge all routes
foreach ($apiV1Routes as $method => $methodRoutes) {
    foreach ($methodRoutes as $path => $handler) {
        $routes[$method][$path] = $handler;
    }
}

foreach ($apiV2Routes as $method => $methodRoutes) {
    foreach ($methodRoutes as $path => $handler) {
        $routes[$method][$path] = $handler;
    }
}

foreach ($activityPubRoutes as $method => $methodRoutes) {
    foreach ($methodRoutes as $path => $handler) {
        $routes[$method][$path] = $handler;
    }
}

return $routes;