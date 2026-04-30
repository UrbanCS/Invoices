@extends('layouts.app')
@section('content')
<div class="flex items-center justify-between"><h1 class="text-3xl font-extrabold text-villeneuve-forest">Clients</h1><a class="btn btn-primary" href="{{ route('clients.create') }}">New Client</a></div>
<div class="panel mt-6 overflow-x-auto"><table class="table w-full"><tr><th>Name</th><th>Tax</th><th>Language</th><th>Status</th></tr>@foreach($clients as $client)<tr><td><a class="font-bold text-villeneuve-green" href="{{ route('clients.show', $client) }}">{{ $client->name }}</a></td><td>{{ $client->tax_profile }}</td><td>{{ $client->default_language }}</td><td>{{ $client->is_active ? 'Active' : 'Archived' }}</td></tr>@endforeach</table></div>{{ $clients->links() }}
@endsection
