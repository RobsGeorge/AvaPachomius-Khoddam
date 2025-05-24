@extends('layouts.app')

@section('content')
<h1>Users</h1>
<a href="{{ route('users.create') }}" class="btn btn-primary">Add User</a>

<table>
    <thead>
        <tr><th>Name</th><th>Email</th><th>Role(s)</th><th>Actions</th></tr>
    </thead>
    <tbody>
        @foreach ($users as $user)
        <tr>
            <td>{{ $user->first_name }} {{ $user->second_name }}</td>
            <td>{{ $user->email }}</td>
            <td>
                @foreach ($user->roles as $role)
                    <span>{{ $role->role_name }}</span>
                @endforeach
            </td>
            <td>
                <a href="{{ route('users.edit', $user->user_id) }}">Edit</a>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
