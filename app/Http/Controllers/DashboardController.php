<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $email = strtolower($user->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter?->name ?? 'A team member',
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $teamSlug = $request->route('current_team');
        $team = null;
        if ($teamSlug) {
            $team = Team::where('slug', $teamSlug)->first();
        }
        if (! $team) {
            $team = $user->currentTeam ?: $user->teams()->first();
        }

        $stats = [
            'totalProducts' => 0,
            'lowStockCount' => 0,
            'todaySales' => 0,
            'topSupplier' => [
                'name' => 'N/A',
                'score' => '0.000',
            ],
        ];

        $recentActivity = [];

        if ($team) {
            $stats['totalProducts'] = Product::where('team_id', $team->id)->where('is_active', true)->count();
            $stats['lowStockCount'] = Product::where('team_id', $team->id)->where('is_active', true)->where('status', 'Low Stock')->count();
            $stats['todaySales'] = (float) Sale::where('team_id', $team->id)->whereDate('sale_date', today())->sum('grand_total');

            $topSupplier = Supplier::where('team_id', $team->id)
                ->where('status', 'Active')
                ->whereNotNull('ahp_score')
                ->orderByDesc('ahp_score')
                ->first(['id', 'name', 'ahp_score']);

            if (! $topSupplier) {
                $topSupplier = Supplier::where('team_id', $team->id)
                    ->orderByDesc('ahp_score')
                    ->first(['id', 'name', 'ahp_score']);
            }

            if ($topSupplier) {
                $stats['topSupplier'] = [
                    'name' => $topSupplier->name,
                    'score' => $topSupplier->ahp_score !== null ? number_format((float) $topSupplier->ahp_score, 3) : 'N/A',
                ];
            }

            // Recent activity feed: merge recent quotations, sales, and stock movements
            $recentQuotations = Quotation::where('team_id', $team->id)
                ->latest()
                ->take(5)
                ->get(['id', 'quotation_number', 'status', 'created_at'])
                ->map(fn ($q) => [
                    'id' => $q->quotation_number,
                    'type' => 'Quotation',
                    'status' => $q->status instanceof \BackedEnum ? $q->status->value : (string) $q->status,
                    'created_at' => $q->created_at,
                ]);

            $recentMovements = StockMovement::where('team_id', $team->id)
                ->latest('movement_date')
                ->take(5)
                ->get(['id', 'reference_number', 'type', 'created_at'])
                ->map(fn ($m) => [
                    'id' => $m->reference_number ?: ('MOV-' . $m->id),
                    'type' => $m->type === 'stock_in' ? 'Stock In' : ($m->type === 'stock_out' ? 'Stock Out' : 'Adjustment'),
                    'status' => 'Completed',
                    'created_at' => $m->created_at,
                ]);

            $recentSales = Sale::where('team_id', $team->id)
                ->latest()
                ->take(5)
                ->get(['id', 'invoice_number', 'status', 'created_at'])
                ->map(fn ($s) => [
                    'id' => $s->invoice_number,
                    'type' => 'Sale',
                    'status' => (string) $s->status,
                    'created_at' => $s->created_at,
                ]);

            $recentActivity = $recentQuotations
                ->concat($recentSales)
                ->concat($recentMovements)
                ->sortByDesc('created_at')
                ->take(5)
                ->values()
                ->map(function ($item) {
                    $status = $item['status'];
                    $colorClass = match ($status) {
                        'Approved', 'Completed', 'In Stock' => 'bg-green-500/10 text-green-500 border-green-500/20',
                        'Pending' => 'bg-[#F8B803]/10 text-[#F8B803] border-[#F8B803]/20',
                        'In Review' => 'bg-blue-500/10 text-blue-500 border-blue-500/20',
                        'Draft' => 'bg-gray-500/10 text-gray-400 border-gray-500/20',
                        'Rejected', 'Cancelled', 'Expired', 'Out of Stock' => 'bg-red-500/10 text-red-500 border-red-500/20',
                        default => 'bg-gray-500/10 text-gray-500 border-gray-500/20',
                    };

                    return [
                        'id' => $item['id'],
                        'type' => $item['type'],
                        'status' => $item['status'],
                        'class' => $colorClass,
                    ];
                })
                ->all();
        }

        $isFirstLogin = (bool) session('is_first_login', false);
        if (! $isFirstLogin && $user) {
            $loginCount = $user->auditLogs()->where('action_type', 'auth.login.success')->count();
            if ($loginCount <= 1 && ($user->last_login_at === null || ($user->created_at && $user->created_at->diffInHours(now()) < 24))) {
                $isFirstLogin = true;
            }
        }

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'isFirstLogin' => $isFirstLogin,
        ]);
    }
}
