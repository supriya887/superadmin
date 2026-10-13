@extends('admin.render')

@section('metaTitle')
    <meta content="{{$title}}" name="description" />
    <title>{{$title}}</title>
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
            <h4 class="content-title mb-2">{{$title}}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{url('dashboard')}}">Admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page"> {{$pageTitle}}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto">
            <div class=" d-flex right-page">
                <a href="{{url('add-admin-branch')}}" class="btn btn-info">
                    <i class="mdi mdi-plus font-size-16 align-middle me-2"></i> Add Branch
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body row">
            <form id="form">
                <div class="col-12">

                    <div class="col-lg-6 mb-3">
                        <div class="form-group">
                            <label for="form-label">id <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="id" id="id"
                                value="@if(isset($update)){{$update->id}}@endif">
                            <div class="text-danger" id="error-id"></div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <div class="form-group">
                            <label for="form-label">branch name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="branch_name" id="branch_name"
                                value="@if(isset($update)){{$update->branch_name}}@endif">
                            <div class="text-danger" id="error-branch_name"></div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <div class="form-group">
                            <label for="form-label">country <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="country" id="country"
                                value="@if(isset($update)){{$update->country}}@endif">
                            <div class="text-danger" id="error-country"></div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <div class="form-group">
                            <label for="form-label">state id <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="state_id" id="state_id"
                                value="@if(isset($update)){{$update->state_id}}@endif">
                            <div class="text-danger" id="error-state_id"></div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <div class="form-group">
                            <label for="form-label">state name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="state_name" id="state_name"
                                value="@if(isset($update)){{$update->state_name}}@endif">
                            <div class="text-danger" id="error-state_name"></div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <div class="form-group">
                            <label for="form-label">status <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="status" id="status"
                                value="@if(isset($update)){{$update->status}}@endif">
                            <div class="text-danger" id="error-status"></div>
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div class="form-group m-4 text-center">
                            @csrf
                            <input type="hidden" name="id" id="id"
                                value="@if(isset($update)){{$update->id}}@endif">
                            <button class="btn btn-success w-md" type="submit">
                                <div class="spinner-border spinner-border-sm float-left mt-1" id="spinner"
                                    style="display:none" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>Submit
                            </button>
                            <a href="{{url('admin_branch-table')}}" class="btn btn-primary w-md">Back </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </div>
@endsection

@section('myJS')
    <script type="text/javascript">
        $(document).ready(function() {
            active("admin-branch-table");
            select2NoSearch("country", "country");
            select2NoSearch("state_id", "state id");
            select2NoSearch("status", "status");
            formSubmit("{{url('admin_branch-validation')}}", "{{url('admin_branch-table')}}");

        });
    </script>
@endsection
