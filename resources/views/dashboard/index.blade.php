@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Merchants</h1>
    <div class="bg-white rounded shadow">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-200 text-gray-700">
                    <th class="p-4">Name</th>
                    <th class="p-4">Email</th>
                    <th class="p-4">Created At</th>
                    <th class="p-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($merchants as $merchant)
                    <tr class="border-t">
                        <td class="p-4 font-semibold">{{ $merchant->name }}</td>
                        <td class="p-4">{{ $merchant->email }}</td>
                        <td class="p-4">{{ $merchant->created_at }}</td>
                        <td class="p-4"><a href="{{ route('dashboard.show', $merchant->id) }}" class="text-blue-600 hover:underline">View Details</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-4 text-center text-gray-500">No merchants found. (Run seeder)</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
