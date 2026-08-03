<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\MonthlyTargetModel;
use CodeIgniter\Database\BaseConnection;

class DashboardController extends BaseController
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    public function index()
    {
        $userId = (int) session()->get('user_id');
        $period = $this->resolvePeriod((string) $this->request->getGet('month'));
        $startDate = $period . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $search = trim((string) $this->request->getGet('q'));
        $type = (string) $this->request->getGet('type');

        if (! in_array($type, ['income', 'expense'], true)) {
            $type = '';
        }

        $categoryModel = new CategoryModel();
        $categories = $categoryModel
            ->where('user_id', $userId)
            ->orderBy('type', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        $monthlyIncome = $this->sumAmount($userId, 'income', $startDate, $endDate);
        $monthlyExpense = $this->sumAmount($userId, 'expense', $startDate, $endDate);
        $totalIncome = $this->sumAmount($userId, 'income');
        $totalExpense = $this->sumAmount($userId, 'expense');
        $target = $this->targetFor($userId, $period);

        return view('dashboard/index', [
            'period'            => $period,
            'periodLabel'       => $this->monthLabel($period),
            'today'             => date('Y-m-d'),
            'search'            => $search,
            'type'              => $type,
            'transactions'      => $this->transactions($userId, $startDate, $endDate, $search, $type),
            'categories'        => $categories,
            'incomeCategories'  => array_values(array_filter($categories, static fn ($row) => $row['type'] === 'income')),
            'expenseCategories' => array_values(array_filter($categories, static fn ($row) => $row['type'] === 'expense')),
            'summary'           => [
                'balance'              => $totalIncome - $totalExpense,
                'monthly_income'       => $monthlyIncome,
                'monthly_expense'      => $monthlyExpense,
                'monthly_net'          => $monthlyIncome - $monthlyExpense,
                'remaining_budget'     => max(0, (float) $target['expense_limit'] - $monthlyExpense),
                'income_progress'      => $this->progress($monthlyIncome, (float) $target['income_target']),
                'expense_progress'     => $this->progress($monthlyExpense, (float) $target['expense_limit']),
                'savings_progress'     => $this->progress($monthlyIncome - $monthlyExpense, (float) $target['savings_target']),
            ],
            'target'            => $target,
            'chartData'         => $this->chartData($userId, $startDate, $endDate),
            'categoryBreakdown' => $this->categoryBreakdown($userId, $startDate, $endDate),
        ]);
    }

    private function sumAmount(int $userId, string $type, ?string $startDate = null, ?string $endDate = null): float
    {
        $builder = $this->db->table('transactions')
            ->selectSum('amount', 'total')
            ->where('user_id', $userId)
            ->where('type', $type);

        if ($startDate && $endDate) {
            $builder
                ->where('transaction_date >=', $startDate)
                ->where('transaction_date <=', $endDate);
        }

        $row = $builder->get()->getRowArray();

        return (float) ($row['total'] ?? 0);
    }

    private function transactions(int $userId, string $startDate, string $endDate, string $search, string $type): array
    {
        $builder = $this->db->table('transactions')
            ->select('transactions.*, categories.name AS category_name, categories.color AS category_color')
            ->join('categories', 'categories.id = transactions.category_id', 'left')
            ->where('transactions.user_id', $userId)
            ->where('transactions.transaction_date >=', $startDate)
            ->where('transactions.transaction_date <=', $endDate);

        if ($search !== '') {
            $builder
                ->groupStart()
                ->like('transactions.description', $search)
                ->orLike('categories.name', $search)
                ->groupEnd();
        }

        if ($type !== '') {
            $builder->where('transactions.type', $type);
        }

        return $builder
            ->orderBy('transactions.transaction_date', 'DESC')
            ->orderBy('transactions.id', 'DESC')
            ->get(80)
            ->getResultArray();
    }

    private function targetFor(int $userId, string $period): array
    {
        $target = (new MonthlyTargetModel())
            ->where('user_id', $userId)
            ->where('period', $period)
            ->first();

        return $target ?: [
            'period'         => $period,
            'income_target'  => 0,
            'expense_limit'  => 0,
            'savings_target' => 0,
        ];
    }

    private function chartData(int $userId, string $startDate, string $endDate): array
    {
        $daysInMonth = (int) date('t', strtotime($startDate));
        $labels = [];
        $income = [];
        $expense = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $labels[] = (string) $day;
            $income[$day] = 0;
            $expense[$day] = 0;
        }

        $rows = $this->db->table('transactions')
            ->select('DAY(transaction_date) AS day, type, SUM(amount) AS total')
            ->where('user_id', $userId)
            ->where('transaction_date >=', $startDate)
            ->where('transaction_date <=', $endDate)
            ->groupBy('DAY(transaction_date), type')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $day = (int) $row['day'];
            $total = (float) $row['total'];

            if ($row['type'] === 'income') {
                $income[$day] = $total;
            } else {
                $expense[$day] = $total;
            }
        }

        return [
            'labels'  => $labels,
            'income'  => array_values($income),
            'expense' => array_values($expense),
        ];
    }

    private function categoryBreakdown(int $userId, string $startDate, string $endDate): array
    {
        return $this->db->table('transactions')
            ->select('COALESCE(categories.name, "Tanpa kategori") AS name, COALESCE(categories.color, "#64748b") AS color, SUM(transactions.amount) AS total', false)
            ->join('categories', 'categories.id = transactions.category_id', 'left')
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', 'expense')
            ->where('transactions.transaction_date >=', $startDate)
            ->where('transactions.transaction_date <=', $endDate)
            ->groupBy('categories.name, categories.color')
            ->orderBy('total', 'DESC')
            ->get(6)
            ->getResultArray();
    }

    private function progress(float $value, float $target): int
    {
        if ($target <= 0) {
            return 0;
        }

        return (int) max(0, min(100, round(($value / $target) * 100)));
    }

    private function resolvePeriod(string $period): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
            return $period;
        }

        return date('Y-m');
    }

    private function monthLabel(string $period): string
    {
        $months = [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        [$year, $month] = explode('-', $period);

        return $months[(int) $month] . ' ' . $year;
    }
}
