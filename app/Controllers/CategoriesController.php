<?php

namespace App\Controllers;

use App\Models\CategoryModel;

class CategoriesController extends BaseController
{
    public function store()
    {
        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'type' => 'required|in_list[income,expense]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userId = (int) session()->get('user_id');
        $type = (string) $this->request->getPost('type');
        $name = trim((string) $this->request->getPost('name'));

        $categoryModel = new CategoryModel();
        $existing = $categoryModel
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return redirect()->back()->with('error', 'Kategori dengan nama itu sudah ada.');
        }

        $categoryModel->insert([
            'user_id' => $userId,
            'name'    => $name,
            'type'    => $type,
            'color'   => $this->nextColor($userId),
        ]);

        return redirect()->back()->with('success', 'Kategori ditambahkan.');
    }

    private function nextColor(int $userId): string
    {
        $palette = ['#2563eb', '#16a34a', '#f97316', '#dc2626', '#0891b2', '#db2777', '#7c3aed', '#0f766e'];
        $count = (new CategoryModel())->where('user_id', $userId)->countAllResults();

        return $palette[$count % count($palette)];
    }
}
