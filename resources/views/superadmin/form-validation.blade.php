@extends('superadmin.layouts.app')

@section('content')
<div class="card">
    <div class="card-header justify-content-between">
        <h4 class="card-title">Browser Defaults</h4>
    </div>
    <div class="card-body">
        <form class="row g-3">
            <div class="col-md-4">
                <label class="form-label">First name</label>
                <input type="text" class="form-control" required />
            </div>
            <div class="col-md-4">
                <label class="form-label">Last name</label>
                <input type="text" class="form-control" required />
            </div>
            <div class="col-md-4">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" required />
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Submit form</button>
            </div>
        </form>
    </div>
</div>
@endsection

