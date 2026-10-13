@extends('superadmin.layouts.app')

@section('content')

<div class="container-fluid mt-3">

    <div class="card shadow-sm">

        <div class="card-header">
            <h4 class="mb-0">
                Notification Preference
            </h4>
        </div>

        <div class="card-body">

            <form id="notificationPreferenceForm">

                @csrf

                <div class="table-responsive">

                    <table class="table table-bordered align-middle text-center">

                        <thead class="table-light">
                            <tr>
                                <th width="25%">Category</th>
                                <th>SMS</th>
                                <th>WhatsApp</th>
                                <th>Email</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach(\App\Models\NotificationPreference::CATEGORIES as $category)

                                <tr>

                                    <td class="text-start fw-bold">
                                        {{ ucfirst(str_replace('_', ' ', $category)) }}
                                    </td>

                                    {{-- SMS --}}
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="notification-checkbox"
                                            data-column="{{ $category }}_sms"
                                            {{ optional($notificationPreference)->{$category.'_sms'} ? 'checked' : '' }}
                                        >
                                    </td>

                                    {{-- WhatsApp --}}
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="notification-checkbox"
                                            data-column="{{ $category }}_whatsapp"
                                            {{ optional($notificationPreference)->{$category.'_whatsapp'} ? 'checked' : '' }}
                                        >
                                    </td>

                                    {{-- Email --}}
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="notification-checkbox"
                                            data-column="{{ $category }}_email"
                                            {{ optional($notificationPreference)->{$category.'_email'} ? 'checked' : '' }}
                                        >
                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection


@push('scripts')

<script>
    $(document).ready(function () {
    
        $('.notification-checkbox').change(function () {
    
            let column = $(this).data('column');
            let value = $(this).is(':checked') ? 1 : 0;
    
            $.ajax({
                url: "{{ route('superadmin.b2b_partners.notification_preference_update') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    partner_id: "{{ optional($notificationPreference)->partner_id }}",
                    column: column,
                    value: value
                },
    
                success: function (response) {
                    console.log(response.message);
                },
    
                error: function (xhr) {
                    console.log(xhr.responseText);
                    console.log(xhr.status);

                    alert(xhr.responseText);
                }
            });
    
        });
    
    });
    </script>

@endpush