@extends('superadmin.layouts.app')

@section('content')
<div data-table data-table-rows-per-page="5" class="card">
    <div class="card-header">
        <h4 class="card-title">Complete Custom Table</h4>
    </div>
    <div class="card-header border-light justify-content-between">
        <div class="d-flex gap-2">
            <div class="app-search">
                <input data-table-search type="search" class="form-control" placeholder="Search product name..." />
                <i class="ti ti-search app-search-icon text-muted"></i>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-centered table-select table-hover w-100 mb-0">
            <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                <tr class="text-uppercase fs-xxs">
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Wireless Earbuds</td>
                    <td>Electronics</td>
                    <td>$59.99</td>
                    <td><span class="badge badge-soft-success fs-xxs">Published</span></td>
                </tr>
                <tr>
                    <td>Modern Lounge Chair</td>
                    <td>Furniture</td>
                    <td>$199.00</td>
                    <td><span class="badge badge-soft-danger fs-xxs">Out of Stock</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

