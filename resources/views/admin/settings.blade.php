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
                        <li class="breadcrumb-item active" aria-current="page"> {{$title}}</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card">
            <div class="card-body row">
                <div class="example">
                    <div class="border">
                        <div class="bg-gray-300 nav-bg">
                            <nav class="nav nav-tabs">
                                <a class="nav-link active" data-bs-toggle="tab" href="#GeneralSet">General</a>
                                <a class="nav-link" data-bs-toggle="tab" href="#SalarySett">Salary</a>
                                <a class="nav-link" data-bs-toggle="tab" href="#api">API</a>
                                <a class="nav-link" data-bs-toggle="tab" href="#CompanyLogo">Logo</a>
                                <a class="nav-link" data-bs-toggle="tab" href="#CompanyEmail">Email</a>
                                <a class="nav-link" data-bs-toggle="tab" href="#CustomSetting">Customs</a>
                                <a class="nav-link" data-bs-toggle="tab" href="#Signature">Signature</a>
                                <a class="nav-link" data-bs-toggle="tab" href="#FinancialYear">Financial Year</a>
                            </nav>
                        </div>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane container active" id="GeneralSet">
                            <form id="general">
                                <div class="row mt-3">
                                    <div class="col-lg-12 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Address <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="address" id="address" rows="4">@if(isset($update)){{$update->address}}@endif</textarea>
                                            <div class="text-danger" id="error-address"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Pincode <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="pincode" id="pincode"
                                                value="@if(isset($update)){{$update->pincode}}@endif">
                                            <div class="text-danger" id="error-pincode"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">GST No <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="gst_no" id="gst_no"
                                                value="@if(isset($update)){{$update->gst_no}}@endif">
                                            <div class="text-danger" id="error-gst_no"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Custom Agent Code <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="custom_agn_code"
                                                id="custom_agn_code"
                                                value="@if(isset($update)){{$update->custom_agn_code}}@endif">
                                            <div class="text-danger" id="error-custom_agn_code"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">SMS Send Start Time <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="time" name="sms_start" id="sms_start"
                                                value="@if(isset($update)){{date('H:i', strtotime($update->sms_start))}}@endif">
                                            <div class="text-danger" id="error-sms_start"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Grid Records <span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="grid_rec" id="grid_rec"
                                                value="@if(isset($update)){{$update->grid_rec}}@endif">
                                            <div class="text-danger" id="error-grid_rec"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Contact No <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="contact" id="contact"
                                                value="@if(isset($update)){{$update->contact}}@endif">
                                            <div class="text-danger" id="error-contact"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Email <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="email" id="email"
                                                value="@if(isset($update)){{$update->email}}@endif">
                                            <div class="text-danger" id="error-email"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Credit Management <span
                                                    class="text-danger">*</span></label>
                                            <select name="credit_manag" id="credit_manag" class="form-control">
                                                <option value="1"
                                                    @if(isset($update) && $update->credit_manag == '1'){{'selected'}}@endif> Yes
                                                </option>
                                                <option value="2"
                                                    @if(isset($update) && $update->credit_manag == '2'){{'selected'}}@endif> No
                                                </option>
                                            </select>
                                            <div class="text-danger" id="error-credit_manag"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Air Export Prefix <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="air_export" id="air_export"
                                                value="@if(isset($update)){{$update->air_export}}@endif">
                                            <div class="text-danger" id="error-air_export"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Sea Export Prefix <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="sea_export" id="sea_export"
                                                value="@if(isset($update)){{$update->sea_export}}@endif">
                                            <div class="text-danger" id="error-sea_export"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">DO Prefix <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="do_pref" id="do_pref"
                                                value="@if(isset($update)){{$update->do_pref}}@endif">
                                            <div class="text-danger" id="error-do_pref"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Expense Advance Head <span
                                                    class="text-danger"></span></label>
                                            <select name="exp_adv" id="exp_adv" class="form-control">
                                            </select>

                                            <div class="text-danger" id="error-exp_adv"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">HAWB Prefix <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="hawb_prefix"
                                                id="hawb_prefix"
                                                value="@if(isset($update)){{$update->hawb_prefix}}@endif">
                                            <div class="text-danger" id="error-hawb_prefix"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">SOA Prefix <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="soa_prefix" id="soa_prefix"
                                                value="@if(isset($update)){{$update->soa_prefix}}@endif">
                                            <div class="text-danger" id="error-soa_prefix"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Payment Voucher Prefix <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="payv_prefix"
                                                id="payv_prefix"
                                                value="@if(isset($update)){{$update->payv_prefix}}@endif">
                                            <div class="text-danger" id="error-payv_prefix"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">CIN No <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="cin_no" id="cin_no"
                                                value="@if(isset($update)){{$update->cin_no}}@endif">
                                            <div class="text-danger" id="error-cin_no"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">IATA Code <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="iata_code" id="iata_code"
                                                value="@if(isset($update)){{$update->iata_code}}@endif">
                                            <div class="text-danger" id="error-iata_code"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">SMS Send End Time <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="time" name="sms_end" id="sms_end"
                                                value="@if(isset($update)){{date('H:i', strtotime($update->sms_end))}}@endif">
                                            <div class="text-danger" id="error-sms_end"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Thumb Hardware <span
                                                    class="text-danger"></span></label>
                                            <select name="thumb_hard" id="thumb_hard" class="form-control">
                                                <option value="1"
                                                    @if(isset($update) && $update->thumb_hard == '1'){{'selected'}}@endif>
                                                    BIOENABLE
                                                </option>
                                                <option value="2"
                                                    @if(isset($update) && $update->thumb_hard == '2'){{'selected'}}@endif> NITGEN
                                                </option>
                                                <option value="3"
                                                    @if(isset($update) && $update->thumb_hard == '3'){{'selected'}}@endif> NITGEN
                                                    PRO
                                                </option>
                                            </select>

                                            <div class="text-danger" id="error-thumb_hard"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Pan No <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="pan_no" id="pan_no"
                                                value="@if(isset($update)){{$update->pan_no}}@endif">
                                            <div class="text-danger" id="error-pan_no"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Round Up <span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="round_up" id="round_up"
                                                value="@if(isset($update)){{$update->round_up}}@endif">
                                            <div class="text-danger" id="error-round_up"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Credit Block Condition <span
                                                    class="text-danger">*</span></label>
                                            <select name="credit_block" id="credit_block" class="form-control">
                                                <option value="1"
                                                    @if(isset($update) && $update->credit_block == '1'){{'selected'}}@endif> Days
                                                </option>
                                                <option value="2"
                                                    @if(isset($update) && $update->credit_block == '2'){{'selected'}}@endif> Amount
                                                </option>
                                                <option value="3"
                                                    @if(isset($update) && $update->credit_block == '3'){{'selected'}}@endif> Both
                                                </option>
                                            </select>
                                            <div class="text-danger" id="error-credit_block"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Air Import Prefix <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="airimp_prifix"
                                                id="airimp_prifix"
                                                value="@if(isset($update)){{$update->airimp_prifix}}@endif">
                                            <div class="text-danger" id="error-airimp_prifix"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Sea Import Prefix <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="seaimp_prefix"
                                                id="seaimp_prefix"
                                                value="@if(isset($update)){{$update->seaimp_prefix}}@endif">
                                            <div class="text-danger" id="error-seaimp_prefix"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Credit Prefix <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="credit_prifix"
                                                id="credit_prifix"
                                                value="@if(isset($update)){{$update->credit_prifix}}@endif">
                                            <div class="text-danger" id="error-credit_prifix"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Booking Request Prefix <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="book_prefix"
                                                id="book_prefix"
                                                value="@if(isset($update)){{$update->book_prefix}}@endif">
                                            <div class="text-danger" id="error-book_prefix"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Debit Prefix <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="debit_prefix"
                                                id="debit_prefix"
                                                value="@if(isset($update)){{$update->debit_prefix}}@endif">
                                            <div class="text-danger" id="error-debit_prefix"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Other Bill Prefix <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="other_prefix"
                                                id="other_prefix"
                                                value="@if(isset($update)){{$update->other_prefix}}@endif">
                                            <div class="text-danger" id="error-other_prefix"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Receipt Voucher Prefix <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="reciept_prefix"
                                                id="reciept_prefix"
                                                value="@if(isset($update)){{$update->reciept_prefix}}@endif">
                                            <div class="text-danger" id="error-reciept_prefix"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group  text-center">
                                            @csrf
                                            <input type="hidden" name="id" id="id"
                                                value="@if(isset($update)){{$update->id}}@endif">

                                            <button class="btn btn-success w-md" type="submit">
                                                <div class="spinner-border spinner-border-sm float-left mt-1"
                                                    id="spinner" style="display:none" role="status">
                                                    <span class="sr-only">Loading...</span>
                                                </div>Submit
                                            </button>
                                            {{-- <a href="{{url('admin-settings-table')}}" class="btn btn-primary w-md">Back
                                            </a> --}}
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane container" id="SalarySett">
                            <form id="salary">
                                <div class="row mt-3">
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Employee Daily Working Hours <span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="emp_dw_hr" id="emp_dw_hr"
                                                value="@if(isset($update)){{$update->emp_dw_hr}}@endif">
                                            <div class="text-danger" id="error-emp_dw_hr"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 align-items-center d-flex">
                                        <div class="form-group ">
                                            <label for="form-label">Assign To All Active Employee <span
                                                    class="text-danger"></span></label>
                                            <input type="checkbox" name="emp_hr_asign" id="emp_hr_asign" value="1"
                                                @if(isset($update) && $update->esi_assign == 1){{'checked'}}@endif>
                                            {{-- <input class="form-control" type="text" name="emp_hr_asign"
                                                id="emp_hr_asign"
                                                value="@if(isset($update)){{$update->emp_hr_asign}}@endif"> --}}
                                            <div class="text-danger" id="error-emp_hr_asign"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3"></div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">ESI % <span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="esi" id="esi"
                                                value="@if(isset($update)){{$update->esi}}@endif">
                                            <div class="text-danger" id="error-esi"></div>
                                        </div>
                                    </div>

                                    <div class="col-lg-3 align-items-center d-flex">
                                        <div class="form-group">
                                            <label for="form-label">Assign To All Active Employee <span
                                                    class="text-danger"></span></label>
                                            <input type="checkbox" name="esi_assign" id="esi_assign" value="1"
                                                @if(isset($update) && $update->esi_assign == 1){{'checked'}}@endif>
                                            {{-- <input class="form-control" type="text" name="esi_assign" id="esi_assign"
                                                value="@if(isset($update)){{$update->esi_assign}}@endif"> --}}
                                            <div class="text-danger" id="error-esi_assign"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3"></div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">PF %<span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="pf" id="pf"
                                                value="@if(isset($update)){{$update->pf}}@endif">
                                            <div class="text-danger" id="error-pf"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 align-items-center d-flex">
                                        <div class="form-group">
                                            <label for="form-label">Assign To All Active Employee <span
                                                    class="text-danger"></span></label>
                                            <input type="checkbox" name="pf_assign" id="pf_assign" value="1"
                                                @if(isset($update) && $update->esi_assign == 1){{'checked'}}@endif>
                                            {{-- <input class="form-control" type="text"  value="@if(isset($update)){{$update->pf_assign}}@endif"> --}}
                                            <div class="text-danger" id="error-pf_assign"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-3"></div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Section 80C Infrastructure Bonds Limit <span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="selc_ins" id="selc_ins"
                                                value="@if(isset($update)){{$update->selc_ins}}@endif">
                                            <div class="text-danger" id="error-selc_ins"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Section 80C Others Limit <span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="sec_limit" id="sec_limit"
                                                value="@if(isset($update)){{$update->sec_limit}}@endif">
                                            <div class="text-danger" id="error-sec_limit"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Medical Expenses Limit <span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="medic_exp" id="medic_exp"
                                                value="@if(isset($update)){{$update->medic_exp}}@endif">
                                            <div class="text-danger" id="error-medic_exp"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Mediclaim Limit <span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="mediclam_limit"
                                                id="mediclam_limit"
                                                value="@if(isset($update)){{$update->mediclam_limit}}@endif">
                                            <div class="text-danger" id="error-mediclam_limit"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Home Loan Interest Limit <span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="home_loan" id="home_loan"
                                                value="@if(isset($update)){{$update->home_loan}}@endif">
                                            <div class="text-danger" id="error-home_loan"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">ESI Limit <span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="esi_limit" id="esi_limit"
                                                value="@if(isset($update)){{$update->esi_limit}}@endif">
                                            <div class="text-danger" id="error-esi_limit"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Conveyance Allowance Limit <span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="conv_allow" id="conv_allow"
                                                value="@if(isset($update)){{$update->conv_allow}}@endif">
                                            <div class="text-danger" id="error-conv_allow"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">E.Cess On TDS <span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="tds" id="tds"
                                                value="@if(isset($update)){{$update->tds}}@endif">
                                            <div class="text-danger" id="error-tds"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Present Day Calculation <span
                                                    class="text-danger">*</span></label>
                                            <select name="present_day" id="present_day" class="form-control">
                                                <option value="1"
                                                    @if(isset($update) && $update->present_day == '1'){{'selected'}}@endif> Days
                                                </option>
                                                <option value="2"
                                                    @if(isset($update) && $update->present_day == '2'){{'selected'}}@endif> Time
                                                </option>
                                            </select>
                                            <div class="text-danger" id="error-present_day"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group  text-center">
                                            @csrf
                                            <input type="hidden" name="id" id="id"
                                                value="@if(isset($update)){{$update->id}}@endif">

                                            <button class="btn btn-success w-md" type="submit">
                                                <div class="spinner-border spinner-border-sm float-left mt-1"
                                                    id="spinner" style="display:none" role="status">
                                                    <span class="sr-only">Loading...</span>
                                                </div>Submit
                                            </button>
                                            <a href="{{url('admin_settings-table')}}" class="btn btn-primary w-md">Back
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane container" id="api">
                            <form id="apisett">
                                <div class="row mt-3">
                                    <div class="col-lg-12 mb-3">
                                        <h4>SMS</h4>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Promotional URL <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="prom_url" id="prom_url"
                                                value="@if(isset($update)){{$update->prom_url}}@endif">
                                            <div class="text-danger" id="error-prom_url"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Transactional URL <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="tranc_url" id="tranc_url"
                                                value="@if(isset($update)){{$update->tranc_url}}@endif">
                                            <div class="text-danger" id="error-tranc_url"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12 mb-3">
                                        <h4>SMS</h4>
                                    </div>

                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Token URL <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="token_url" id="token_url"
                                                value="@if(isset($update)){{$update->token_url}}@endif">
                                            <div class="text-danger" id="error-token_url"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Cancel URL <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="cancel_url" id="cancel_url"
                                                value="@if(isset($update)){{$update->cancel_url}}@endif">
                                            <div class="text-danger" id="error-cancel_url"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Client Id <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="client_id" id="client_id"
                                                value="@if(isset($update)){{$update->client_id}}@endif">
                                            <div class="text-danger" id="error-client_id"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">User Name <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="username" id="username"
                                                value="@if(isset($update)){{$update->username}}@endif">
                                            <div class="text-danger" id="error-username"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12 mb-3">
                                        <h4>Email</h4>
                                    </div>

                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Email Id <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="email_id" id="email_id"
                                                value="@if(isset($update)){{$update->email_id}}@endif">
                                            <div class="text-danger" id="error-email_id"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Email Id Password <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="email_pass" id="email_pass"
                                                value="@if(isset($update)){{$update->smpt_name}}@endif">
                                            <div class="text-danger" id="error-email_pass"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">SMTP Server Name <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="smpt_name" id="smpt_name"
                                                value="@if(isset($update)){{$update->smpt_name}}@endif">
                                            <div class="text-danger" id="error-smpt_name"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">SMTP Port <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="smpt_port" id="smpt_port"
                                                value="@if(isset($update)){{$update->smpt_port}}@endif">
                                            <div class="text-danger" id="error-smpt_port"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">SSL <span class="text-danger"></span></label>
                                            <select name="api_ssl" id="api_ssl" class="form-control">
                                                <option value="1"
                                                    @if(isset($update) && $update->api_ssl == '1'){{'selected'}}@endif> Yes
                                                </option>
                                                <option value="2"
                                                    @if(isset($update) && $update->api_ssl == '2'){{'selected'}}@endif> No
                                                </option>
                                            </select>
                                            <div class="text-danger" id="error-api_ssl"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">IRN URL <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="irn_url" id="irn_url"
                                                value="@if(isset($update)){{$update->irn_url}}@endif">
                                            <div class="text-danger" id="error-irn_url"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Secret Key <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="secret_key" id="secret_key"
                                                value="@if(isset($update)){{$update->secret_key}}@endif">
                                            <div class="text-danger" id="error-secret_key"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Password <span class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="password" id="password"
                                                value="@if(isset($update)){{$update->password}}@endif">
                                            <div class="text-danger" id="error-password"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group  text-center">
                                        @csrf
                                        <input type="hidden" name="id" id="id"
                                            value="@if(isset($update)){{$update->id}}@endif">
                                        <button class="btn btn-success w-md" type="submit">
                                            <div class="spinner-border spinner-border-sm float-left mt-1" id="spinner"
                                                style="display:none" role="status">
                                                <span class="sr-only">Loading...</span>
                                            </div>Submit
                                        </button>
                                        <a href="{{url('admin_settings-table')}}" class="btn btn-primary w-md">Back
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane container" id="CompanyLogo">
                            <form id="logosett">
                                <div class="row mt-3">
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Logo <span class="text-danger"></span></label>
                                            <input type="file" name="logo" class="form-control" id="logo"
                                                data-default-file="@if(isset($update)){{asset('images/' . $update->logo)}}@endif"
                                                data-allowed-file-extensions="png jpg jpeg" data-errors-position="outside"
                                                accept="image/*" data-show-remove="false" />
                                            <input type="hidden" id="logo_file" name="logo_file"
                                                value="@if(isset($update)){{$update->logo}}@endif">
                                            @if(isset($update))
                                                <a href="javascript:void(0);" class="btn btn-primary view mt-2 w-md"
                                                    id="{{asset('images/' . $update->logo)}}">View</a>
                                                    @endif
                                                <div class="text-danger" id="error-logo"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Logo Transparent<span
                                                    class="text-danger"></span></label>
                                            <input type="file" name="logotr" class="form-control" id="logotr"
                                                data-default-file="@if(isset($update)){{asset('images/' . $update->logotr)}}@endif"
                                                data-allowed-file-extensions="png jpg jpeg" data-errors-position="outside"
                                                accept="image/*" data-show-remove="false" />
                                            <input type="hidden" id="logotr_file" name="logotr_file"
                                                value="@if(isset($update)){{$update->logotr}}@endif">
                                                @if(isset($update))
                                                <a href="javascript:void(0);" class="btn btn-primary view mt-2 w-md"  id="{{asset('images/' . $update->logotr)}}">View</a>
                                                @endif
                                            <div class="text-danger" id="error-logo"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Fav Icon <span class="text-danger"></span></label>
                                            <input type="file" name="favicon" class="form-control" id="favicon"
                                                data-default-file="@if(isset($update)){{asset('images/' . $update->favicon)}}@endif"
                                                data-allowed-file-extensions="png jpg jpeg" data-errors-position="outside"
                                                accept="image/*" data-show-remove="false" />
                                            <input type="hidden" id="favicon_file" name="favicon_file"
                                                value="@if(isset($update)){{$update->favicon}}@endif">
                                            @if(isset($update))
                                            <a href="javascript:void(0);" class="btn btn-primary view mt-2 w-md"  id="{{asset('images/' . $update->favicon)}}">View</a>
                                            @endif
                                            <div class="text-danger" id="error-logo"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group  text-center">
                                            @csrf
                                            <input type="hidden" name="id" id="id"
                                                value="@if(isset($update)){{$update->id}}@endif">
                                            <button class="btn btn-success w-md" type="submit">
                                                <div class="spinner-border spinner-border-sm float-left mt-1"
                                                    id="spinner" style="display:none" role="status">
                                                    <span class="sr-only">Loading...</span>
                                                </div>Submit
                                            </button>
                                            <a href="{{url('admin_settings-table')}}" class="btn btn-primary w-md">Back
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane container" id="CompanyEmail">
                            <form id="emailsett">
                                <div class="row mt-3">
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Outstanding Email <span
                                                    class="text-danger"></span></label>
                                            <input type="checkbox" name="outs_email" id="outs_email" value="1"
                                                @if(isset($update) && $update->outs_email == 1){{'checked'}}@endif>
                                            <div class="text-danger" id="error-outs_email"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Email Send Day 1 <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="emailday_one"
                                                id="emailday_one"
                                                value="@if(isset($update)){{$update->emailday_one}}@endif">
                                            <div class="text-danger" id="error-emailday_one"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Open Job Card <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="open_jobcard"
                                                id="open_jobcard"
                                                value="@if(isset($update)){{$update->open_jobcard}}@endif">
                                            <div class="text-danger" id="error-open_jobcard"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Email Send Day 2 <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="emailday_two"
                                                id="emailday_two"
                                                value="@if(isset($update)){{$update->emailday_two}}@endif">
                                            <div class="text-danger" id="error-emailday_two"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group  text-center">
                                            @csrf
                                            <input type="hidden" name="id" id="id"
                                                value="@if(isset($update)){{$update->id}}@endif">

                                            <button class="btn btn-success w-md" type="submit">
                                                <div class="spinner-border spinner-border-sm float-left mt-1"
                                                    id="spinner" style="display:none" role="status">
                                                    <span class="sr-only">Loading...</span>
                                                </div>Submit
                                            </button>
                                            <a href="{{url('admin_settings-table')}}" class="btn btn-primary w-md">Back
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            </form>
                            <div class="row mt-3">
                                <div class="table-responsive">
                                    <table class="table border-top-0 table-bordered text-nowrap border-bottom" id="datatable">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Email</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                 </div>
                            </div>
                            <div class="row mt-3 d-flex align-items-center">
                                <div class="col-md-3 form-group text-left">
                                    <label for="form-label">Email Access<span class="text-danger"></span></label>
                                    <select name="access_name" id="access_name" class="form-control">
                                    @foreach ($access as $Row)
                                    <option value="{{$Row->id}}" {{-- @if(isset($update) && $update->exp_adv == $Row->id){{'selected'}}@endif --}}>
                                        {{$Row->access_name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                    <div class="col-md-3 form-group text-left">
                                        <label for="form-label">Email Signature <span class="text-danger"></span></label>
                                        <input class="form-control" type="text" name="email_sign" id="email_sign" value="">
                                    </div>
                                    <div class="col-md-3 form-group text-left mt-4">
                                        <button class="btn btn-success w-md" type="submit">
                                            <div class="spinner-border spinner-border-sm float-left mt-1"
                                            id="spinner" style="display:none" role="status">
                                            <span class="sr-only">Loading...</span></div>Submit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <div class="tab-pane container" id="CustomSetting">
                            <form id="customsett">
                                <div class="row mt-3">
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">SeaImport Sender Id <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="seaimp_sender"
                                                id="seaimp_sender"
                                                value="@if(isset($update)){{$update->seaimp_sender}}@endif">
                                            <div class="text-danger" id="error-seaimp_sender"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">SeaExport Sender Id <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="seaexp_sender"
                                                id="seaexp_sender"
                                                value="@if(isset($update)){{$update->seaexp_sender}}@endif">
                                            <div class="text-danger" id="error-seaexp_sender"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">SeaImport Receiver Id <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="seaimp_rec" id="seaimp_rec"
                                                value="@if(isset($update)){{$update->seaimp_rec}}@endif">
                                            <div class="text-danger" id="error-seaimp_rec"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">SeaExport Receiver Id <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="seaexp_rec" id="seaexp_rec"
                                                value="@if(isset($update)){{$update->seaexp_rec}}@endif">
                                            <div class="text-danger" id="error-seaexp_rec"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group  text-center">
                                            @csrf
                                            <input type="hidden" name="id" id="id"
                                                value="@if(isset($update)){{$update->id}}@endif">
                                            <button class="btn btn-success w-md" type="submit">
                                                <div class="spinner-border spinner-border-sm float-left mt-1"
                                                    id="spinner" style="display:none" role="status">
                                                    <span class="sr-only">Loading...</span>
                                                </div>Submit
                                            </button>
                                            <a href="{{url('admin_settings-table')}}"
                                                class="btn btn-primary w-md">Back </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane container" id="Signature">
                            <form id="signaturesett">
                                <div class="row mt-3 d-flex justify-content-center">
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Email Signature <span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="email_sign" id="email_sign"
                                                value="@if(isset($update)){{$update->email_sign}}@endif">
                                            <div class="text-danger" id="error-email_sign"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group  text-center">
                                        @csrf
                                        <input type="hidden" name="id" id="id"
                                            value="@if(isset($update)){{$update->id}}@endif">

                                        <button class="btn btn-success w-md" type="submit">
                                            <div class="spinner-border spinner-border-sm float-left mt-1" id="spinner"
                                                style="display:none" role="status">
                                                <span class="sr-only">Loading...</span>
                                            </div>Submit
                                        </button>
                                        <a href="{{url('admin_settings-table')}}" class="btn btn-primary w-md">Back
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane container" id="FinancialYear">
                            <form id="FinancialYearsett">
                                <div class="row mt-3 d-flex justify-content-center">
                                    <div class="col-lg-4 mb-3">
                                        <div class="form-group">
                                            <label for="form-label">Financial Year <span
                                                    class="text-danger"></span></label>
                                            <select name="fin_year" id="fin_year" class="form-control">
                                                @if(!empty($yearlist))
                                                    @foreach($yearlist as $yearRow)
                                                    <option @if(isset($update) && $update->fin_year == $yearRow->id){{ 'selected'}}@endif value="{{ $yearRow->id }}">{{ $yearRow->short_year }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                            <div class="text-danger" id="error-fin_year"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group  text-center">
                                        @csrf
                                        <input type="hidden" name="id" id="id"
                                            value="@if(isset($update)){{$update->id}}@endif">

                                        <button class="btn btn-success w-md" type="submit">
                                            <div class="spinner-border spinner-border-sm float-left mt-1" id="spinner"
                                                style="display:none" role="status">
                                                <span class="sr-only">Loading...</span>
                                            </div>Submit
                                        </button>
                                        <a href="{{url('admin_settings-table')}}" class="btn btn-primary w-md">Back
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
    <div class="modal fade bs-example-modal-center" id="showModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body" id="modalBody"> </div>
            </div>
        </div>
    </div>
@endsection

@section('myJS')
    <script type="text/javascript">
        $(document).ready(function() {
            $(".view").on("click", function() {
                var url = $(this).attr('id');
                $("#modalBody").html('');
                $("#modalBody").html('<img src="' + url + '" class="w-100" />');
                $("#showModal").modal('show');
            });
            active("add-admin-settings");
            select2NoSearch("credit_manag", "Credit Management");
            select2NoSearch("access_name", "Select");
            select2("exp_adv", "select");
            select2("fin_year", "");
            select2NoSearch("credit_block", "Credit Block Condition");
            select2NoSearch("present_day", "Present Day Calculation");
            select2NoSearch("api_ssl", "SSL");
            get_accountHead('exp_adv');
            $("#general").on("submit", function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{url('general-sett-validation')}}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function(data) {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true);
                    },
                    success: function(data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                } else {
                                    $("#error-" + key).html(" ");
                                    $("#" + key).removeClass("border-danger");
                                }
                            });
                        }
                        if (data.success) {
                            $("#form .form-control").removeClass("border-danger");
                            $("#form .text-danger").html(" ");
                            console.log(data);
                            alertify.success(data.success);

                            setTimeout(function() {
                                window.location.href =
                                    "{{url('add-admin-settings')}}";
                            }, 1000);

                        }
                    },
                    error: function(error) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        errorMessage(error.status);
                    }
                });
            });
            $("#salary").on("submit", function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{url('salary-sett-validation')}}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function(data) {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true);
                    },
                    success: function(data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                } else {
                                    $("#error-" + key).html(" ");
                                    $("#" + key).removeClass("border-danger");
                                }
                            });
                        }
                        if (data.success) {
                            $("#form .form-control").removeClass("border-danger");
                            $("#form .text-danger").html(" ");
                            console.log(data);
                            alertify.success(data.success);

                            setTimeout(function() {
                                window.location.href =
                                    "{{url('add-admin-settings')}}";
                            }, 1000);

                        }
                    },
                    error: function(error) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        errorMessage(error.status);
                    }
                });
            });
            $("#apisett").on("submit", function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{url('apisett-validation')}}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function(data) {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true);
                    },
                    success: function(data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                } else {
                                    $("#error-" + key).html(" ");
                                    $("#" + key).removeClass("border-danger");
                                }
                            });
                        }
                        if (data.success) {
                            $("#form .form-control").removeClass("border-danger");
                            $("#form .text-danger").html(" ");
                            console.log(data);
                            alertify.success(data.success);

                            setTimeout(function() {
                                window.location.href =
                                    "{{url('add-admin-settings')}}";
                            }, 1000);

                        }
                    },
                    error: function(error) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        errorMessage(error.status);
                    }
                });
            });
            $("#logosett").on("submit", function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{url('logo-validation')}}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function(data) {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true);
                    },
                    success: function(data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                } else {
                                    $("#error-" + key).html(" ");
                                    $("#" + key).removeClass("border-danger");
                                }
                            });
                        }
                        if (data.success) {
                            $("#form .form-control").removeClass("border-danger");
                            $("#form .text-danger").html(" ");
                            console.log(data);
                            alertify.success(data.success);

                            setTimeout(function() {
                                window.location.href =
                                    "{{url('add-admin-settings')}}";
                            }, 1000);

                        }
                    },
                    error: function(error) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        errorMessage(error.status);
                    }
                });
            });
            $("#emailsett").on("submit", function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{url('email-validation')}}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function(data) {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true);
                    },
                    success: function(data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                } else {
                                    $("#error-" + key).html(" ");
                                    $("#" + key).removeClass("border-danger");
                                }
                            });
                        }
                        if (data.success) {
                            $("#form .form-control").removeClass("border-danger");
                            $("#form .text-danger").html(" ");
                            console.log(data);
                            alertify.success(data.success);

                            setTimeout(function() {
                                window.location.href =
                                    "{{url('add-admin-settings')}}";
                            }, 1000);

                        }
                    },
                    error: function(error) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        errorMessage(error.status);
                    }
                });
            });
            $("#customsett").on("submit", function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{url('custom-validation')}}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function(data) {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true);
                    },
                    success: function(data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                } else {
                                    $("#error-" + key).html(" ");
                                    $("#" + key).removeClass("border-danger");
                                }
                            });
                        }
                        if (data.success) {
                            $("#form .form-control").removeClass("border-danger");
                            $("#form .text-danger").html(" ");
                            console.log(data);
                            alertify.success(data.success);

                            setTimeout(function() {
                                window.location.href =
                                    "{{url('add-admin-settings')}}";
                            }, 1000);

                        }
                    },
                    error: function(error) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        errorMessage(error.status);
                    }
                });
            });
            $("#signaturesett").on("submit", function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{url('signature-validation')}}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function(data) {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true);
                    },
                    success: function(data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                } else {
                                    $("#error-" + key).html(" ");
                                    $("#" + key).removeClass("border-danger");
                                }
                            });
                        }
                        if (data.success) {
                            $("#form .form-control").removeClass("border-danger");
                            $("#form .text-danger").html(" ");
                            console.log(data);
                            alertify.success(data.success);

                            setTimeout(function() {
                                window.location.href =
                                    "{{url('add-admin-settings')}}";
                            }, 1000);

                        }
                    },
                    error: function(error) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        errorMessage(error.status);
                    }
                });
            });

            $("#FinancialYearsett").on("submit", function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{url('financial-year-validation')}}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function(data) {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true);
                    },
                    success: function(data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                } else {
                                    $("#error-" + key).html(" ");
                                    $("#" + key).removeClass("border-danger");
                                }
                            });
                        }
                        if (data.success) {
                            $("#form .form-control").removeClass("border-danger");
                            $("#form .text-danger").html(" ");
                            console.log(data);
                            alertify.success(data.success);

                            setTimeout(function() {
                                window.location.href =
                                    "{{url('add-admin-settings')}}";
                            }, 1000);

                        }
                    },
                    error: function(error) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        errorMessage(error.status);
                    }
                });
            });

            function get_accountHead(id, title) {
                $('#' + id).select2({
                    ajax: {
                        url: "{{ url('getAllAccountHead') }}",
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        type: "post",
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                searchTerm: params.term,
                                _token: "{{ csrf_token() }}"
                            };
                        },
                        processResults: function(response) {
                            return {
                                results: response
                            };
                        },
                        cache: true
                    },
                    placeholder: title,
                    maximumInputLength: Infinity,
                    allowClear: true
                });
            }
        });
    </script>
@endsection
