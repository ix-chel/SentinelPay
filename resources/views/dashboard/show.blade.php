@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('dashboard.index') }}" class="text-blue-600 hover:underline">&larr; Back to Merchants</a>
    </div>

    <h1 class="text-3xl font-bold mb-6">{{ $merchant->name }}</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- API Keys -->
        <div class="bg-white rounded shadow p-6">
            <h2 class="text-xl font-semibold border-b pb-2 mb-4">API Keys</h2>
            <ul class="mb-4">
                @forelse($merchant->apiKeys as $key)
                    <li class="mb-2 text-sm break-all">
                        <strong>Hashed:</strong> <span class="font-mono text-gray-500">{{ Str::limit($key->hashed_key, 20) }}</span> <br>
                        <strong>Rate Limit:</strong> {{ $key->rate_limit ?? 100 }} rq/min | 
                        <strong>Last Used:</strong> {{ $key->last_used_at ?? 'Never' }}
                    </li>
                @empty
                    <li class="text-gray-500">No API Keys.</li>
                @endforelse
            </ul>
        </div>

        <!-- Webhooks -->
        <div class="bg-white rounded shadow p-6">
            <h2 class="text-xl font-semibold border-b pb-2 mb-4">Webhooks</h2>
            <ul class="mb-4">
                @forelse($webhooks as $wh)
                    <li class="mb-2 text-sm break-all">
                        <strong>URL:</strong> <span class="font-mono">{{ $wh->url }}</span> <br>
                        <strong>Events:</strong> {{ implode(', ', $wh->events ?? []) }}
                    </li>
                @empty
                    <li class="text-gray-500">No Webhooks configured.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <!-- Recent Transfers -->
    <div class="bg-white rounded shadow mt-6 mb-8">
        <h2 class="text-xl font-semibold border-b p-4 bg-gray-50">Recent Transfers</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-4">ID</th>
                        <th class="p-4">Amount</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers as $tx)
                        <tr class="border-t">
                            <td class="p-4 font-mono text-xs">{{ $tx->id }}</td>
                            <td class="p-4 font-mono">{{ $tx->amount }} {{ $tx->currency }}</td>
                            <td class="p-4 uppercase font-bold {{ $tx->status === 'succeeded' ? 'text-green-600' : 'text-yellow-600' }}">{{ $tx->status }}</td>
                            <td class="p-4 text-gray-500">{{ $tx->created_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-4 text-center text-gray-500">No transfers yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">
            {{ $transfers->links() }}
        </div>
    </div>

    <!-- Recent Webhook Deliveries -->
    @if($deliveries->isNotEmpty())
    <div class="bg-white rounded shadow mb-8">
        <h2 class="text-xl font-semibold border-b p-4 bg-gray-50">Webhook Delivery Logs</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-4">Event</th>
                        <th class="p-4">Status Code</th>
                        <th class="p-4">Payload ID</th>
                        <th class="p-4">Time</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deliveries as $del)
                        <tr class="border-t">
                            <td class="p-4">{{ $del->event }}</td>
                            <td class="p-4 {{ $del->successful ? 'text-green-600' : 'text-red-600' }}">{{ $del->response_status }}</td>
                            <td class="p-4 font-mono text-xs">{{ $del->request_payload['id'] ?? 'N/A' }}</td>
                            <td class="p-4 text-gray-500">{{ $del->created_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection
