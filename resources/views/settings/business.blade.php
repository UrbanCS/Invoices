@extends('layouts.app')
@section('content')
<h1 class="text-3xl font-extrabold text-villeneuve-forest">Business Settings</h1>
<form class="panel mt-6 grid gap-4 p-6 md:grid-cols-2" method="post" enctype="multipart/form-data" action="{{ route('settings.business.update') }}">@csrf @method('put')
@foreach(['legal_name'=>'Legal name','display_name'=>'Display name','address'=>'Address','city'=>'City','province'=>'Province','postal_code'=>'Postal code','phone'=>'Phone','email'=>'Email','gst_number'=>'GST/TPS number','qst_number'=>'QST/TVQ number'] as $field=>$label)<div><label class="label">{{ $label }}</label><input class="mt-1 w-full" name="{{ $field }}" value="{{ old($field,$settings->$field) }}"></div>@endforeach
<div><label class="label">Language</label><select class="mt-1 w-full" name="default_language"><option value="fr" @selected($settings->default_language==='fr')>French</option><option value="en" @selected($settings->default_language==='en')>English</option></select></div>
<div><label class="label">Logo</label><input class="mt-1 w-full" type="file" name="logo"></div>
<div class="md:col-span-2"><label class="label">Payment instructions</label><textarea class="mt-1 w-full" name="default_payment_instructions">{{ old('default_payment_instructions',$settings->default_payment_instructions) }}</textarea></div>
<div class="md:col-span-2"><label class="label">Thank-you message</label><textarea class="mt-1 w-full" name="default_thank_you_message">{{ old('default_thank_you_message',$settings->default_thank_you_message) }}</textarea></div>
<button class="btn btn-primary">Save Settings</button>
</form>
@endsection
