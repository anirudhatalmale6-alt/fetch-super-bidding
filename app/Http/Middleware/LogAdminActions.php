<?php

namespace App\Http\Middleware;

use App\Models\Admin\AdminActionLog;
use Closure;
use Illuminate\Http\Request;

class LogAdminActions
{
    /**
     * Actions that should be logged
     */
    protected array $loggableActions = [
        'overrideStage',
        'blacklistCompany',
        'adjustFees',
        'cancelOrder',
        'reassignCompany',
        'updateRoute',
        'updateHub',
        'processRefund',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only log successful requests
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->logAction($request, $response);
        }

        return $response;
    }

    /**
     * Log the admin action
     */
    protected function logAction(Request $request, $response): void
    {
        $action = $request->route()->getActionMethod();

        if (!in_array($action, $this->loggableActions)) {
            return;
        }

        $adminId = auth()->id();
        $targetId = $request->route('id') ?? $request->route('requestId') ?? $request->route('companyId');
        $targetType = $this->getTargetType($request);

        AdminActionLog::log(
            $adminId,
            $this->getActionName($action),
            $this->getActionCategory($action),
            $this->getActionDescription($action, $request),
            [
                'target_type' => $targetType,
                'target_id' => $targetId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );
    }

    /**
     * Get target type from request
     */
    protected function getTargetType(Request $request): ?string
    {
        $routeName = $request->route()->getName() ?? '';

        if (str_contains($routeName, 'order')) return 'request';
        if (str_contains($routeName, 'company')) return 'company';
        if (str_contains($routeName, 'route')) return 'route';
        if (str_contains($routeName, 'hub')) return 'hub';

        return null;
    }

    /**
     * Get normalized action name
     */
    protected function getActionName(string $action): string
    {
        return match($action) {
            'overrideStage' => 'stage_override',
            'blacklistCompany' => 'company_blacklist',
            'adjustFees' => 'fee_adjustment',
            'cancelOrder' => 'order_cancellation',
            'reassignCompany' => 'company_reassignment',
            'updateRoute' => 'route_update',
            'updateHub' => 'hub_update',
            'processRefund' => 'payment_refund',
            default => $action,
        };
    }

    /**
     * Get action category
     */
    protected function getActionCategory(string $action): string
    {
        return match($action) {
            'overrideStage' => AdminActionLog::CATEGORY_STAGE,
            'blacklistCompany', 'reassignCompany' => AdminActionLog::CATEGORY_COMPANY,
            'adjustFees', 'processRefund' => AdminActionLog::CATEGORY_PAYMENT,
            'cancelOrder' => AdminActionLog::CATEGORY_ORDER,
            'updateRoute' => AdminActionLog::CATEGORY_ROUTE,
            'updateHub' => AdminActionLog::CATEGORY_HUB,
            default => 'other',
        };
    }

    /**
     * Get action description
     */
    protected function getActionDescription(string $action, Request $request): string
    {
        $targetId = $request->route('id') ?? $request->route('requestId') ?? $request->route('companyId');

        return match($action) {
            'overrideStage' => "Stage manually overridden for request {$targetId}",
            'blacklistCompany' => "Company {$targetId} blacklisted",
            'adjustFees' => "Fees adjusted for request {$targetId}",
            'cancelOrder' => "Order {$targetId} cancelled by admin",
            'reassignCompany' => "Request {$targetId} reassigned to different company",
            'updateRoute' => "Route {$targetId} updated",
            'updateHub' => "Hub {$targetId} updated",
            'processRefund' => "Refund processed for request {$targetId}",
            default => "Admin action: {$action}",
        };
    }
}
