@extends('admin.layouts.app')

@section('title', 'Partners')

@section('css')
<style>
    /* Table Card Styles */
    .table-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.02);
        padding: 20px 0;
    }

    .table-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 25px 20px 25px;
        border-bottom: 1px solid #f0f0f0;
    }

    .toolbar-search {
        position: relative;
        width: 300px;
    }

    .toolbar-search i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #aaa;
        font-size: 13px;
    }

    .toolbar-search input {
        width: 100%;
        border: 1px solid #f0f0f0;
        background-color: #fbfbfb;
        border-radius: 6px;
        padding: 8px 15px 8px 40px;
        font-size: 13px;
        color: #444;
    }

    .toolbar-search input:focus {
        outline: none;
        border-color: #ddd;
    }

    .toolbar-actions {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 13px;
        color: #666;
    }

    .toolbar-actions select {
        border: 1px solid #f0f0f0;
        background-color: #fbfbfb;
        border-radius: 6px;
        padding: 8px 30px 8px 15px;
        font-size: 13px;
        color: #444;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 16px;
        cursor: pointer;
    }

    .toolbar-actions select:focus {
        outline: none;
        border-color: #ddd;
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }

    .custom-table th {
        font-size: 11px;
        font-weight: 600;
        color: #444;
        text-transform: uppercase;
        padding: 15px 25px;
        border-bottom: 1px solid #f0f0f0;
        background: #fdfdfd;
        letter-spacing: 0.5px;
    }

    .custom-table th i {
        color: #bbb;
        font-size: 10px;
        margin-left: 5px;
    }

    .custom-table td {
        padding: 15px 25px;
        vertical-align: middle;
        font-size: 13px;
        color: #555;
        border-bottom: 1px solid #f5f5f5;
    }

    .custom-table tbody tr:hover {
        background-color: #fcfcfc;
    }

    .user-td {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        color: #333;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }

    .status-pending { background-color: #fff8dd; color: #ff9900; }
    .status-approved { background-color: #e5f7ed; color: #28a745; }
    .status-rejected { background-color: #fee2e2; color: #dc2626; }

    .action-btn {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: #f4f6f8;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #888;
        font-size: 12px;
        margin: 0 3px;
        cursor: pointer;
        transition: 0.2s;
    }

    .action-btn:hover {
        background-color: #e2e6ea;
        color: #333;
    }

    .table-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 25px 0 25px;
    }

    .table-info {
        font-size: 12px;
        color: #888;
    }

    .pagination {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 0;
        gap: 5px;
    }

    .page-item {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #eee;
        border-radius: 4px;
        font-size: 12px;
        color: #555;
        text-decoration: none;
        cursor: pointer;
        transition: 0.2s;
    }

    .page-item.active {
        background-color: var(--primary-blue);
        color: #fff;
        border-color: var(--primary-blue);
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Partners</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Partners</li>
        </ol>
    </nav>
</div>

<div class="table-card">
    <div class="table-toolbar">
        <div class="toolbar-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search Partner">
        </div>
        
        <div class="toolbar-actions">
            <span>Filter By:</span>
            <select style="width: 120px;">
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            
            <select style="width: 70px;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            
            <a href="#" class="btn btn-submit py-2 px-4 rounded-pill m-0" style="font-size: 13px;">Add New Partner</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>NAME <i class="fas fa-arrows-alt-v"></i></th>
                    <th>CONTACT <i class="fas fa-arrows-alt-v"></i></th>
                    <th>GSTIN <i class="fas fa-arrows-alt-v"></i></th>
                    <th>STATUS <i class="fas fa-arrows-alt-v"></i></th>
                    <th>APPROVE BY <i class="fas fa-arrows-alt-v"></i></th>
                    <th>DATE <i class="fas fa-arrows-alt-v"></i></th>
                    <th class="text-center">...</th>
                </tr>
            </thead>
            <tbody>
                <!-- Row 1 -->
                <tr>
                    <td>Janak Goyal</td>
                    <td>janak@goyal.com<br><small class="text-muted">9876543210</small></td>
                    <td>24AAAAA0000A1Z5</td>
                    <td><span class="status-badge status-approved">Approved</span></td>
                    <td>Admin</td>
                    <td>24-03-2024 10:30 AM</td>
                    <td class="text-center">
                        <button class="action-btn"><i class="fas fa-eye"></i></button>
                        <button class="action-btn text-success"><i class="fas fa-check"></i></button>
                    </td>
                </tr>
                <!-- Row 2 -->
                <tr>
                    <td>Rahul Singh</td>
                    <td>rahul@test.com<br><small class="text-muted">9988776655</small></td>
                    <td>27BBBBB1111B1Z2</td>
                    <td><span class="status-badge status-pending">Pending</span></td>
                    <td>-</td>
                    <td>-</td>
                    <td class="text-center">
                        <button class="action-btn"><i class="fas fa-eye"></i></button>
                        <button class="action-btn text-success"><i class="fas fa-check"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <div class="table-info">
            Showing 1 to 2 of 2 records
        </div>
        <div class="pagination">
            <a href="#" class="page-item"><i class="fas fa-chevron-left"></i></a>
            <a href="#" class="page-item active">1</a>
            <a href="#" class="page-item"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
</div>
@endsection
