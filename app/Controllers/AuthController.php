<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\UserModel;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('is_logged_in')) {
            return redirect()->to('/');
        }

        return view('auth/login');
    }

    public function authenticate()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $user = (new UserModel())
            ->where('email', strtolower((string) $this->request->getPost('email')))
            ->first();

        if (! $user || ! password_verify((string) $this->request->getPost('password'), $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Email atau password belum cocok.');
        }

        $this->startUserSession($user);

        return redirect()->to('/');
    }

    public function register()
    {
        if (session()->get('is_logged_in')) {
            return redirect()->to('/');
        }

        return view('auth/register');
    }

    public function store()
    {
        $rules = [
            'name'             => 'required|min_length[2]|max_length[120]',
            'email'            => 'required|valid_email|max_length[190]|is_unique[users.email]',
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
        ];

        $messages = [
            'email' => [
                'is_unique' => 'Email ini sudah terdaftar.',
            ],
            'password_confirm' => [
                'matches' => 'Konfirmasi password belum sama.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userModel = new UserModel();
        $userId = $userModel->insert([
            'name'          => trim((string) $this->request->getPost('name')),
            'email'         => strtolower(trim((string) $this->request->getPost('email'))),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
        ], true);

        $user = $userModel->find($userId);
        $this->seedDefaultCategories((int) $userId);
        $this->startUserSession($user);

        return redirect()->to('/')->with('success', 'Akun berhasil dibuat.');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/login');
    }

    private function startUserSession(array $user): void
    {
        session()->regenerate();
        session()->set([
            'is_logged_in' => true,
            'user_id'      => $user['id'],
            'user_name'    => $user['name'],
            'user_email'   => $user['email'],
        ]);
    }

    private function seedDefaultCategories(int $userId): void
    {
        $categoryModel = new CategoryModel();
        $categories = [
            ['name' => 'Gaji', 'type' => 'income', 'color' => '#16a34a'],
            ['name' => 'Bonus', 'type' => 'income', 'color' => '#0891b2'],
            ['name' => 'Usaha', 'type' => 'income', 'color' => '#2563eb'],
            ['name' => 'Makanan', 'type' => 'expense', 'color' => '#f97316'],
            ['name' => 'Transportasi', 'type' => 'expense', 'color' => '#dc2626'],
            ['name' => 'Tagihan', 'type' => 'expense', 'color' => '#7c3aed'],
            ['name' => 'Belanja', 'type' => 'expense', 'color' => '#db2777'],
            ['name' => 'Tabungan', 'type' => 'expense', 'color' => '#0f766e'],
        ];

        foreach ($categories as $category) {
            $category['user_id'] = $userId;
            $categoryModel->insert($category);
        }
    }
}
