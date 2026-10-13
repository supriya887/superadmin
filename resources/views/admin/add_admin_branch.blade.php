@extends('admin.render')

@section('metaTitle')
    <meta content="{{ $title }}" name="description" />
    <title>{{ $title }}</title>
@endsection

@section('cssLinks')
@endsection

@section('myCSS')
@endsection
@section('pageTitle')
@endsection
@section('cardHeading')
@endsection
@section('cardBody')
    <div class="main-container container-fluid">
        <div class="breadcrumb-header justify-content-between">
            <div>
                <h4 class="content-title mb-2">{{ $title }}</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="javascript:void(0);">Admin</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><a href="{{ url('admin-branch-table') }}">
                                {{ $title }}</a></li>
                    </ol>
                </nav>
            </div>

        </div>

        <form id="form">
            <div class="card">
                <div class="card-body row">
                    <div class="d-flex justify-content-center">
                        <div class="col-md-9 row">
                            <div class="col-lg-4 mb-3">
                                <div class="form-group">
                                    <label for="form-label">Branch Name <span class="text-danger">*</span></label>

                                    <input class="form-control" type="text" name="branch_name" id="branch_name"
                                        value="@if (isset($update)) {{ $update->branch_name }} @endif">
                                    <div class="text-danger" id="error-branch_name"></div>
                                </div>
                            </div>
                            <div class="col-lg-4 mb-3">
                                <div class="form-group">
                                    <label for="form-label">Country <span class="text-danger">*</span></label>
                                    <select name="country" id="country" class="form-control country">
                                        <option value=""></option>
                                        @foreach ($country as $Row)
                                            <option value="{{ $Row->location_id }}"
                                                @if (isset($update) && $update->country == $Row->location_id) {{ 'selected' }} @endif>
                                                {{ $Row->location_name }}</option>
                                        @endforeach
                                    </select>

                                    <div class="text-danger" id="error-country"></div>
                                </div>
                            </div>
                            <div class="col-lg-4 mb-3" id="StateId" style="display:none">
                                <div class="form-group">
                                    <label for="form-label">State <span class="text-danger">*</span></label>
                                    <select name="state_id" id="state_id" class="form-control">
                                    </select>
                                    <div class="text-danger" id="error-state_id"></div>
                                </div>
                            </div>
                            <div class="col-lg-4 mb-3" id="StateName">
                                <div class="form-group">
                                    <label for="form-label">State Name <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" name="state_name" id="state_name"
                                        value="@if (isset($update)) {{ $update->state_name }} @endif">
                                    <div class="text-danger" id="error-state_name"></div>
                                </div>
                            </div>
                            <div class="col-lg-4 mb-3" id="city">
                                <div class="form-group">
                                    <label for="form-label">City<span class="text-danger"></span></label>
                                    <input class="form-control" type="text" name="city" id="city"
                                        value="@if (isset($update)) {{ $update->city }} @endif">
                                    <div class="text-danger" id="error-city"></div>
                                </div>
                            </div>
                            <div class="col-lg-4 mb-3">
                                <div class="form-group">
                                    <label for="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-control" name="status" id="status">
                                        <option value="1"
                                            @if (isset($update) && $update->status == '1') {{ 'selected' }} @endif>Active</option>
                                        <option value="0"
                                            @if (isset($update) && $update->status == '0') {{ 'selected' }} @endif>Inactive</option>
                                    </select>
                                    <div class="text-danger" id="error-status"></div>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="form-group m-4 text-center">
                                    @csrf
                                    <input type="hidden" name="id" id="id"
                                        value="@if (isset($update)) {{ $update->id }} @endif">
                                    <button class="btn btn-success w-md" type="submit">
                                        <div class="spinner-border spinner-border-sm float-left mt-1" id="spinner"
                                            style="display:none" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>Submit
                                    </button>
                                    <a href="{{ url('admin-branch-table') }}" class="btn btn-primary w-md">Back </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('myJS')
    <script type="text/javascript">
        $(document).ready(function() {
            $("#state_id").on("change", function() {
                var stateval = $("#state_id option:selected").text();
                $("#state_name").val(stateval);
            });
            countryChange();
            $("#country").on("change", function() {
                countryChange();
            });
            function countryChange(params) {
                var id = $('#country').val();
                if (id == 1) {
                    get_state(id);
                    $("#StateId").show();
                } else {
                    $("#StateName").show();
                    $("#StateId").hide();
                }
            }
            active("admin-branch-table");

            select2("country", "");
            select2("state_id", "");
            select2NoSearch("status", "");

            formSubmit("{{ url('admin-branch-validation') }}", "{{ url('admin-branch-table') }}");
            // var id = $("#state_id option:selected").text();
            // get_state(id);
            setTimeout(() => {
                // $('#state_id').val(id).trigger('change');
            }, 1000);

            function get_state(id) {
                $("#StateId").show();
                $("#StateName").hide();
                $.ajax({
                    url: '{{ url('get-state') }}',
                    type: 'POST',
                    data: {
                        id: id,
                        _token: '{{ csrf_token() }}',
                    },
                    dataType: 'json',
                    success: function(data) {
                        $('#state_id').html(' ');
                        $('#state_id').html(data.html);

                    }
                });
            }
        });
    </script>
@endsection
