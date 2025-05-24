@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-3xl p-6 bg-white rounded shadow-md">
    <h2 class="text-2xl mb-6 font-bold">Unverified Users</h2>

    @if(session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif

    @if($unverifiedUsers->isEmpty())
        <p>No unverified users at the moment.</p>
    @else
        <table class="w-full table-auto border-collapse border border-gray-300">
            <thead>
                <tr>
                    <th class="border border-gray-300 px-4 py-2">ID</th>
                    <th class="border border-gray-300 px-4 py-2">Name</th>
                    <th class="border border-gray-300 px-4 py-2">Email</th>
                    <th class="border border-gray-300 px-4 py-2">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($unverifiedUsers as $user)
                <tr>
                    <td class="border border-gray-300 px-4 py-2">{{ $user->id }}</td>
                    <td class="border border-gray-300 px-4 py-2">{{ $user->first_name }} {{ $user->second_name }}</td>
                    <td class="border border-gray-300 px-4 py-2">{{ $user->email }}</td>
                    <td class="border border-gray-300 px-4 py-2">
                        <form method="POST" action="{{ route('admin.users.approve', $user->id) }}">
                            @csrf
                            <button type="submit"
                                class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
                                Approve
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
