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
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between pb-0">
                <div>
                    <h4 class="page-title mb-0 font-size-18 text-dark">{{ $pageTitle }}</h4>

                    <ol class="breadcrumb p-0 mt-2">
                        <li class="breadcrumb-item"><a href="">Admin</a></li>
                        <li class="breadcrumb-item active"> {{ $pageTitle }}</li>
                    </ol>
                </div>
                <div class="page-title-right">
                    <a href="{{ url('add-admin_branch') }}" class="btn btn-primary waves-effect waves-light">
                        <i class="mdi mdi-plus font-size-16 align-middle me-2"></i> Add admin_branch
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('cardHeading')
@endsection
@section('cardBody')
    <div class="card">
        <div class="card-body">
            <table id="datatable" class="table table-hover dataTable table-striped w-100">
                <thead>
                    <tr>
                        <th>id</th>
                        <th>branch name</th>
                        <th>country</th>
                        <th>state id</th>
                        <th>state name</th>
                        <th>status</th>
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
                            <td>{{ $row->country }}</td>
                            <td>{{ $row->state_id }}</td>
                            <td>{{ $row->state_name }}</td>
                            <td>{{ $row->status }}</td>
                            <td>
                                <div class="btn-list">
                                    <a class="btn btn-primary" href="{{ url('add-admin_branch/' . $row->id) }}"><i class="mdi mdi-pencil"></i> </a>
                                    <a class="btn btn-danger delete" id="{{ $row->id }}" href="javascript:void(0);"><i class="mdi mdi-delete"></i> </a>
                                </div>
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
@endsection

@section('myJS')
    <script type="text/javascript">
        $(document).ready(function() {
            dt("datatable");
            deleteRow("delete", "{{ url('delete-admin_branch') }}", "Delete Successfully.");
        });
    </script>
@endsection
