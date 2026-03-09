<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Web;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\View;
use NanoPub\Core\Session;
use NanoPub\Services\AuthService;
use NanoPub\Exceptions\AuthenticationException;
use NanoPub\Exceptions\ValidationException;

/**
 * Handles authentication web UI endpoints.
 */
final class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Show login form.
     * 
     * GET /login
     */
    public function showLogin(Request $request): string
    {
        if ($this->authService->isLoggedIn()) {
            Response::redirect(url('/'));
            return '';
        }

        return View::render('web/auth/login');
    }

    /**
     * Handle login.
     * 
     * POST /login
     */
    public function login(Request $request): string
    {
        $data = $request->json() ?? $_POST;
        $login = $data['login'] ?? $_POST['login'] ?? '';
        $password = $data['password'] ?? $_POST['password'] ?? '';

        try {
            $this->authService->login($login, $password);
            Response::redirect(url('/'));
            return '';
        } catch (AuthenticationException $e) {
            return View::render('web/auth/login', [
                'error' => $e->getMessage(),
                'login' => $login,
            ]);
        }
    }

    /**
     * Show register form.
     * 
     * GET /register
     */
    public function showRegister(Request $request): string
    {
        if ($this->authService->isLoggedIn()) {
            Response::redirect(url('/'));
            return '';
        }

        return View::render('web/auth/register');
    }

    /**
     * Handle registration.
     * 
     * POST /register
     */
    public function register(Request $request): string
    {
        $data = $request->json() ?? $_POST;
        $input = !empty($data) ? $data : $_POST;
        
        try {
            $accountId = $this->authService->register($input);
            $this->authService->login($input['email'], $input['password']);
            Response::redirect(url('/'));
            return '';
        } catch (ValidationException $e) {
            return View::render('web/auth/register', [
                'errors' => $e->getErrors(),
                'error' => $e->getMessage(),
                'input' => $input,
            ]);
        }
    }

    /**
     * Handle logout.
     * 
     * POST /logout
     */
    public function logout(Request $request): string
    {
        $this->authService->logout();
        Response::redirect(url('/'));
        return '';
    }
}
