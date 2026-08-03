<?php

namespace App\Controllers;

use App\Models\MonthlyTargetModel;

class TargetsController extends BaseController
{
    public function save()
    {
        $period = (string) $this->request->getPost('period');

        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            return redirect()->back()->with('error', 'Periode target tidak valid.');
        }

        $rules = [
            'income_target'  => 'permit_empty',
            'expense_limit'  => 'permit_empty',
            'savings_target' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userId = (int) session()->get('user_id');
        $targetModel = new MonthlyTargetModel();
        $existing = $targetModel
            ->where('user_id', $userId)
            ->where('period', $period)
            ->first();

        $data = [
            'user_id'        => $userId,
            'period'         => $period,
            'income_target'  => $this->amount('income_target'),
            'expense_limit'  => $this->amount('expense_limit'),
            'savings_target' => $this->amount('savings_target'),
        ];

        if ($existing) {
            $targetModel->update($existing['id'], $data);
        } else {
            $targetModel->insert($data);
        }

        return redirect()->back()->with('success', 'Target bulanan diperbarui.');
    }

    private function amount(string $key): float
    {
        $raw = trim((string) $this->request->getPost($key));

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
