@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-3xl p-6 bg-white rounded shadow-md" dir="rtl">
    <h2 class="text-2xl font-bold mb-6 text-right">الملف الشخصي</h2>

    @if(session('success'))
        <div class="mb-4 text-green-600 text-right">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-right">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>- {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

        <div class="relative w-32 h-32 rounded-full overflow-hidden mx-auto mb-4 cursor-pointer">
        @if($user->profile_photo)
            <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="صورة الملف الشخصي"
                class="w-full h-full object-cover border rounded-full">
        @else
            <div class="w-full h-full bg-gray-200 flex items-center justify-center rounded-full">
                <span class="text-gray-500">لا صورة</span>
            </div>
        @endif

        <form action="{{ route('profile.picture.update') }}" method="POST" enctype="multipart/form-data" class="absolute inset-0">
            @csrf
            @method('PUT')

            <!-- Invisible file input filling the circle -->
            <input 
                type="file" 
                name="profile_picture" 
                class="opacity-0 w-full h-full cursor-pointer" 
                onchange="this.form.submit()" 
                title="اضغط لتحميل صورة جديدة"
            />
        </form>
    </div>

    <div class="text-right leading-relaxed">
        <p><strong>الاسم الكامل:</strong> {{ $fullName }}</p>
        <p><strong>تاريخ الميلاد:</strong> {{ $user->date_of_birth ?? 'غير متوفر' }}</p>
        <p><strong>الرقم القومي:</strong> {{ $user->national_id ?? 'غير متوفر' }}</p>
        <p><strong>رقم الهاتف:</strong> {{ $user->mobile_number ?? 'غير متوفر' }}</p>
        <p><strong>الوظيفة:</strong> {{ $user->job ?? 'غير متوفر' }}</p>
        <p><strong>البريد الإلكتروني:</strong> {{ $user->email }}</p>
        <p><strong>تاريخ التسجيل:</strong> {{ $user->created_at->format('Y-m-d') }}</p>
    </div>

    @php
    use Illuminate\Support\Facades\Auth;

    $user = Auth::user();
    $attendanceUrlWithUser = route('attendance.sessions', ['user_id' => $user->user_id], true);
    @endphp
    
<style>
    /* Modal styles (unchanged) */
    .modal {
        display: none; 
        position: fixed; 
        z-index: 1000; 
        padding-top: 60px; 
        left: 0;
        top: 0;
        width: 100%; 
        height: 100%; 
        overflow: auto; 
        background-color: rgba(0,0,0,0.7);
    }
    .modal-content {
        margin: auto;
        display: block;
        max-width: 90vw;   
        max-height: 90vh;  
        width: auto;
        height: auto;
        border-radius: 10px;
        background: white;
        padding: 20px;
        text-align: center;
        box-sizing: border-box;
    }
    .modal-content svg {
        max-width: 100%;
        height: auto;
    }
    .close-btn {
        position: fixed;
        top: 20px;
        right: 30px;
        color: white;
        font-size: 30px;
        font-weight: bold;
        cursor: pointer;
        z-index: 1100;
    }
    .qr-clickable {
        cursor: pointer;
    }
</style>

<div class="text-center mt-6">
    <h3 class="mb-4 font-bold text-lg">رمز QR Code لتسجيل الحضور</h3>
    <div class="inline-block p-4 bg-white border rounded shadow">
        <div class="qr-clickable" onclick="openModal()">
            {!! QrCode::size(200)->generate($attendanceUrlWithUser) !!}
        </div>
    </div>
    <p class="mt-2 text-sm text-gray-600">امسح هذا الرمز لتسجيل الحضور</p>
</div>

<!-- Modal -->
<div id="qrModal" class="modal" onclick="closeModal(event)">
    <span class="close-btn" onclick="closeModal(event)">&times;</span>
    <div class="modal-content">
        {!! QrCode::size(400)->generate($attendanceUrlWithUser) !!}
        <p class="mt-2 text-lg">امسح هذا الرمز لتسجيل الحضور</p>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('qrModal').style.display = 'block';
    }
    function closeModal(event) {
        if(event.target.id === 'qrModal' || event.target.classList.contains('close-btn')) {
            document.getElementById('qrModal').style.display = 'none';
        }
    }
</script>

</div>
@endsection
