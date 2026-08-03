<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\TransactionModel;

class TransactionsController extends BaseController
{
    public function store()
    {
        $rules = [
            'transaction_date' => 'required|valid_date[Y-m-d]',
            'description'      => 'required|max_length[255]',
            'amount'           => 'required',
            'type'             => 'required|in_list[income,expense]',
            'category_id'      => 'permit_empty|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userId = (int) session()->get('user_id');
        $type = (string) $this->request->getPost('type');
        $categoryId = $this->request->getPost('category_id') ?: null;
        $amount = $this->rupiahAmount($this->request->getPost('amount'));

        if ($amount <= 0) {
            return redirect()->back()->withInput()->with('error', 'Nominal harus lebih dari Rp 0.');
        }

        if ($categoryId !== null && ! $this->categoryBelongsToUser((int) $categoryId, $userId, $type)) {
            return redirect()->back()->withInput()->with('error', 'Kategori tidak ditemukan untuk akun ini.');
        }

        (new TransactionModel())->insert([
            'user_id'          => $userId,
            'category_id'      => $categoryId,
            'transaction_date' => $this->request->getPost('transaction_date'),
            'description'      => trim((string) $this->request->getPost('description')),
            'amount'           => $amount,
            'type'             => $type,
        ]);

        return redirect()->back()->with('success', 'Transaksi tersimpan.');
    }

    public function delete(int $id)
    {
        $userId = (int) session()->get('user_id');
        $transactionModel = new TransactionModel();
        $transaction = $transactionModel
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (! $transaction) {
            return redirect()->back()->with('error', 'Transaksi tidak ditemukan.');
        }

        $transactionModel->delete($id);

        return redirect()->back()->with('success', 'Transaksi dihapus.');
    }

    private function categoryBelongsToUser(int $categoryId, int $userId, string $type): bool
    {
        return (new CategoryModel())
            ->where('id', $categoryId)
            ->where('user_id', $userId)
            ->where('type', $type)
            ->first() !== null;
    }

    private function rupiahAmount($value): float
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return 0;
        }

        $candidate = preg_replace('/[^\d.,]/', '', $raw);

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $candidate) === 1) {
            return (float) str_replace('.', '', $candidate);
        }

        if (preg_match('/^\d{1,3}(,\d{3})+$/', $candidate) === 1) {
            return (float) str_replace(',', '', $candidate);
        }

        if (is_numeric($raw)) {
            return (float) $raw;
        }

        $normalized = str_replace('.', '', $candidate);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^0-9.]/', '', $normalized);

        if ($normalized !== '' && substr_count($normalized, '.') <= 1 && is_numeric($normalized)) {
            return (float) $normalized;
        }

        $digits = preg_replace('/\D/', '', $raw);

        return $digits === '' ? 0 : (float) $digits;
    }
}
