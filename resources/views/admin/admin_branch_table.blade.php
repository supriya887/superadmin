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
                        <li class="breadcrumb-item"><a href="{{ url('dashboard') }}">Admin</a></li>
                        <li class="breadcrumb-item active" aria-current="page"> {{ $pageTitle }}</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-auto">
                <div class=" d-flex right-page">
                    @if (permission(25, 'Add'))
                    <a href="{{ url('add-admin-branch') }}" class="btn btn-info">
                        <i class="mdi mdi-plus font-size-16 align-middle me-2"></i> Add Branch
                    </a>
                    @endif
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table border-top-0 table-bordered text-nowrap border-bottom" id="datatable">
                        <thead>
                            <tr>
                                <th>id</th>
                                <th>branch name</th>
                                <th>country</th>
                                <th>state name</th>
                                <th>status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                DB::connection()->getPDO();
                                $i = 0;
                            @endphp
                            @foreach ($result as $row)
                                <tr>
                                    <td>{{ $row->id }}</td>
                                    <td>{{ $row->branch_name }}</td>
                                    <td>{{ $row->countryname }}</td>
                                    <td>{{ $row->state_name }}</td>
                                    <td>
                                    @if ($row->status == 1)
                                        <span class="btn  btn-success-light">Active</span>
                                    @else
                                        <span class="btn  btn-danger-light">Inactive</span>
                                    @endif
                                    </td>
                                    <td>
                                        @if ((permission(25, 'Edit')) && (permission(25, 'Delete')))
                                        <div class="btn-icon-list">

                                                @if (permission(25, 'Edit'))
                                                <a class="btn btn-icon  btn-primary"
                                                    href="{{ url('add-admin-branch/' . $row->id) }}"><i
                                                        class="mdi mdi-pencil"></i> </a>
                                                @endif
                                                @if (permission(25, 'Delete'))
                                                <a class="btn btn-icon  btn-danger delete" id="{{ $row->id }}"
                                                    href="javascript:void(0);"><i class="mdi mdi-delete"></i> </a>
                                                @endif

                                        </div>
                                        @endif
                                    </td>
                                </tr>
                                @php
                                    $i++;

                                @endphp
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('myJS')
    <script type="text/javascript">
        $(document).ready(function() {
            active("admin-branch-table");
            dt("datatable");
            deleteRow("delete", "{{ url('delete-admin_branch') }}", "Delete Successfully.");
        });
    </script>
@endsection
