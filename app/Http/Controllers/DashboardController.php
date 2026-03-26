<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Transfer;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

class DashboardController extends Controller
{
    public function index()
    {
        $merchants = Merchant::all();

        return view('dashboard.index', compact('merchants'));
    }

    public function show($id)
    {
        $merchant = Merchant::findOrFail($id);
        $transfers = Transfer::where('merchant_id', $id)->orderBy('created_at', 'desc')->paginate(10);
        $webhooks = WebhookEndpoint::where('merchant_id', $id)->get();

        $webhookIds = $webhooks->pluck('_id')->toArray();
        $deliveries = collect();
        if (! empty($webhookIds)) {
            $deliveries = WebhookDelivery::whereIn('webhook_endpoint_id', $webhookIds)->orderBy('created_at', 'desc')->take(20)->get();
        }

        return view('dashboard.show', compact('merchant', 'transfers', 'webhooks', 'deliveries'));
    }
}
