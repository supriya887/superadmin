@extends('superadmin.layouts.app')

@section('content')
<div class="main-container container-fluid mt-4">

    <div class="breadcrumb-header justify-content-between">
        <div>
            <h4 class="content-title mb-2">{{ $title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.providers.index') }}">Providers</a></li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto">
            <a href="{{ route('superadmin.providers.index') }}" class="btn btn-primary">
                <i class="mdi mdi-arrow-left me-1"></i> Back to Providers
            </a>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">{{ $pageTitle }} for {{ $provider->name }}</h3>
            <button type="button" class="btn btn-primary" id="addNewVariantBtn"
                data-bs-toggle="modal" data-bs-target="#variantModal">Add New Variant</button>
        </div>
        <div class="card-body">
            @php
                $childrenByParent = collect($variants ?? [])->groupBy(function ($item) {
                    return $item->parent_variant_id ?: 0;
                });
                $variants_plans = $variants_plans ?? [];
            @endphp
            <div class="table-responsive">
                <table class="table table-custom table-centered table-select table-hover w-100 mb-0" id="variantsTable">
                    <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                        <tr>
                            <th>Sr. No.</th>
                            <th>Variant Name</th>
                            <th>Hierarchy</th>
                            <th>Status</th>
                            <th class="text-center" style="width: 20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sortableVariants">
                        @if(empty($variants) || $variants->isEmpty())
                        <tr>
                            <td colspan="5" class="text-center">No variants found for this provider.</td>
                        </tr>
                        @else
                            @php
                                $mainSrNo = 1;
                            @endphp
                            @foreach(($childrenByParent[0] ?? collect()) as $variant)
                                @php
                                    $hasChildren = !empty($childrenByParent[$variant->id]) && count($childrenByParent[$variant->id]) > 0;
                                    $hasPlans = !empty($variants_plans) && !empty($variants_plans[$variant->id]) && count($variants_plans[$variant->id]) > 0;
                                @endphp
                                <tr class="variant-row" data-variant-id="{{ $variant->id }}" data-main-id="{{ $variant->id }}" data-parent-id="" data-order="{{ $variant->variant_order ?? 0 }}">
                                    <td>{{ $mainSrNo }}</td>
                                    <td>
                                        <i class="fa fa-arrows-alt handle" style="cursor:move"></i>
                                        <strong>{{ $variant->name }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info">Main variant</span>
                                    </td>
                                    <td>
                                        @if($variant->status == 'active')
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            @if(!$hasChildren)
                                                <a href="{{ url('superadmin/plans/'. $provider->id . '/' . $variant->id) }}"
                                                    class="btn btn-light btn-icon btn-sm rounded-circle"
                                                    data-bs-toggle="tooltip"
                                                    title="View Plans">
                                                    <i class="ti ti-eye fs-lg"></i>
                                                </a>
                                                @if(!$hasPlans)
                                                    <a href="{{ url('superadmin/plans/'. $provider->id . '/' . $variant->id . '/add') }}"
                                                        class="btn btn-light btn-icon btn-sm rounded-circle"
                                                        data-bs-toggle="tooltip"
                                                        title="Add Plan">
                                                        <i class="ti ti-plus fs-lg"></i>
                                                    </a>
                                                @else
                                                    <button type="button" class="btn btn-light btn-icon btn-sm rounded-circle" data-bs-toggle="tooltip" title="This variant is already in use by a plan" disabled>
                                                        <i class="ti ti-plus fs-lg text-muted"></i>
                                                    </button>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                            <button type="button"
                                                class="btn btn-light btn-icon btn-sm rounded-circle edit-variant"
                                                data-id="{{ $variant->id }}"
                                                data-provider-id="{{ $provider->id }}"
                                                title="Edit Variant">
                                                <i class="ti ti-edit fs-lg"></i>
                                            </button>
                                            @if(!$hasPlans && !$hasChildren)
                                            <button type="button"
                                                class="btn btn-light btn-icon btn-sm rounded-circle delete-variant"
                                                data-id="{{ $variant->id }}"
                                                data-bs-toggle="tooltip"
                                                title="Delete Variant">
                                                <i class="ti ti-trash fs-lg"></i>
                                            </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @php
                                    $subSrNo = 1;
                                @endphp
                                @foreach(($childrenByParent[$variant->id] ?? collect()) as $subVariant)
                                    @php
                                        $subHasPlans = !empty($variants_plans) && !empty($variants_plans[$subVariant->id]) && count($variants_plans[$subVariant->id]) > 0;
                                    @endphp
                                    <tr class="variant-row" data-variant-id="{{ $subVariant->id }}" data-main-id="{{ $variant->id }}" data-parent-id="{{ $variant->id }}" data-order="{{ $subVariant->variant_order ?? 0 }}">
                                        <td>{{ $mainSrNo . '.' . $subSrNo }}</td>
                                        <td class="ps-4">
                                            <i class="fa fa-arrows-alt handle" style="cursor:move"></i>
                                            - {{ $subVariant->name }}
                                        </td>
                                        <td><span class="badge bg-secondary-subtle text-secondary">Sub-variant of {{ $variant->name }}</span></td>
                                        <td>
                                            @if($subVariant->status == 'active')
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <a href="{{ url('superadmin/plans/'. $provider->id . '/' . $subVariant->id) }}"
                                                    class="btn btn-light btn-icon btn-sm rounded-circle"
                                                    data-bs-toggle="tooltip"
                                                    title="View Plans">
                                                    <i class="ti ti-eye fs-lg"></i>
                                                </a>
                                                @if(!$subHasPlans)
                                                    <a href="{{ url('superadmin/plans/'. $provider->id . '/' . $subVariant->id . '/add') }}"
                                                        class="btn btn-light btn-icon btn-sm rounded-circle"
                                                        data-bs-toggle="tooltip"
                                                        title="Add Plan">
                                                        <i class="ti ti-plus fs-lg"></i>
                                                    </a>
                                                @else
                                                    <button type="button" class="btn btn-light btn-icon btn-sm rounded-circle" data-bs-toggle="tooltip" title="This sub-variant is already in use by a plan" disabled>
                                                        <i class="ti ti-plus fs-lg text-muted"></i>
                                                    </button>
                                                @endif
                                                <button type="button"
                                                    class="btn btn-light btn-icon btn-sm rounded-circle edit-variant"
                                                    data-id="{{ $subVariant->id }}"
                                                    data-provider-id="{{ $provider->id }}"
                                                    title="Edit Variant">
                                                    <i class="ti ti-edit fs-lg"></i>
                                                </button>
                                                @if(!$subHasPlans)
                                                <button type="button"
                                                    class="btn btn-light btn-icon btn-sm rounded-circle delete-variant"
                                                    data-id="{{ $subVariant->id }}"
                                                    data-bs-toggle="tooltip"
                                                    title="Delete Variant">
                                                    <i class="ti ti-trash fs-lg"></i>
                                                </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @php
                                        $subSrNo++;
                                    @endphp
                                @endforeach
                                @php
                                    $mainSrNo++;
                                @endphp
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Variant Modal --}}
    <div class="modal fade" id="variantModal" tabindex="-1" aria-labelledby="variantModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="variantModalLabel">Add/Edit Variant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="variantForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="id" id="variantId" value="">
                        <input type="hidden" name="provider_id" value="{{ $provider->id }}">
                        <input type="hidden" name="parent_variant_id" id="parent_variant_id" value="">
                        <input type="hidden" id="main_category_id" value="">

                        <div class="mb-3">
                            <label for="name" class="form-label">Variant Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="text-danger" id="error-name"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Main Category</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control" id="main_category_label" value="None (Create as Main category)" readonly>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="chooseMainCategoryBtn">Choose</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="sub_category_id" class="form-label">Sub Category</label>
                            <select class="form-control" id="sub_category_id" disabled>
                                <option value="">Select Main Category first</option>
                            </select>
                            <div class="form-text">If no sub category is selected, the new variant will be created directly under the selected main category.</div>
                            <div class="text-danger" id="error-parent_variant_id"></div>
                        </div>
                        <div class="mb-3">
                            <label for="v_status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="v_status" name="v_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <div class="text-danger" id="error-v_status"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveVariantBtn">Save Variant
                            <div class="spinner-border spinner-border-sm" role="status" style="display:none;" id="variantSpinner">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Main Category Picker --}}
    <div class="modal fade" id="mainCategoryModal" tabindex="-1" aria-labelledby="mainCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mainCategoryModalLabel">Choose Main Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group" id="mainCategoryList">
                        <button type="button" class="list-group-item list-group-item-action main-category-option" data-id="" data-name="None (Create as Main category)">
                            None (Create as Main category)
                        </button>
                        @foreach(($rootVariants ?? []) as $rootVariant)
                            <button type="button" class="list-group-item list-group-item-action main-category-option"
                                data-id="{{ $rootVariant->id }}"
                                data-name="{{ $rootVariant->name }}">
                                {{ $rootVariant->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3 text-end">
        <span id="variantDataRefreshLabel" class="text-muted" style="font-size:0.925rem;">
            Last refreshed: <span id="variantDataRefreshTime">Never</span>
        </span>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
@php
    $variants_plans = $variants_plans ?? [];
    $variantsForJs = ($variants ?? collect())->map(function ($v) use ($variants_plans) {
        return [
            'id' => (int) $v->id,
            'sr_no' => (int) $v->sr_no,
            'name' => (string) $v->name,
            'parent_variant_id' => $v->parent_variant_id ? (int) $v->parent_variant_id : null,
            'status' => (string) $v->status,
            'variant_order' => $v->variant_order ?? 0,
            'has_plans' => isset($variants_plans[$v->id]) && count($variants_plans[$v->id]) > 0 ? 1 : 0,
        ];
    })->values();
    $variantsPlansJs = $variants_plans;
@endphp

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" />
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
(function () {
    'use strict';

    var variantsData = @json($variantsForJs);
    var saveUrl = @json(route('superadmin.providers.variants.save'));
    var editUrlBase = @json(url('superadmin/providers/variants'));
    var updateOrderUrl = @json(route('superadmin.providers.variants.updateOrder'));
    var csrfToken = @json(csrf_token());

    function updateVariantDataRefreshTime(timeValue) {
        var d = timeValue ? new Date(timeValue) : new Date();
        function pad(n) { return n < 10 ? '0' + n : n; }
        var text = d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()) + " " +
            pad(d.getHours()) + ":" + pad(d.getMinutes()) + ":" + pad(d.getSeconds());
        var el = document.getElementById('variantDataRefreshTime');
        if (el) el.textContent = text;
    }
    window.updateVariantDataRefreshTime = updateVariantDataRefreshTime;

    function showBsModal(el) {
        if (!el) return;
        try {
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
                return;
            }
        } catch (e) {}
        try {
            if (window.jQuery && jQuery.fn && typeof jQuery.fn.modal === 'function') {
                jQuery(el).modal('show');
                return;
            }
        } catch (e) {}
        el.classList.add('show');
        el.style.display = 'block';
        el.removeAttribute('aria-hidden');
        el.setAttribute('aria-modal', 'true');
        document.body.classList.add('modal-open');
        if (!document.getElementById(el.id + '-manual-backdrop')) {
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = el.id + '-manual-backdrop';
            document.body.appendChild(backdrop);
        }
    }

    function hideBsModal(el) {
        if (!el) return;
        try {
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).hide();
                return;
            }
        } catch (e) {}
        try {
            if (window.jQuery && jQuery.fn && typeof jQuery.fn.modal === 'function') {
                jQuery(el).modal('hide');
                return;
            }
        } catch (e) {}
        el.classList.remove('show');
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        var backdrop = document.getElementById(el.id + '-manual-backdrop');
        if (backdrop) backdrop.remove();
    }

    function clearVariantErrors() {
        ['error-name', 'error-parent_variant_id', 'error-v_status'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = '';
        });
    }

    function findVariantById(id) {
        id = parseInt(id, 10);
        for (var i = 0; i < variantsData.length; i++) {
            if (variantsData[i].id === id) return variantsData[i];
        }
        return null;
    }

    function syncParentVariantId() {
        var subId = document.getElementById('sub_category_id').value;
        var mainId = document.getElementById('main_category_id').value;
        var parent = document.getElementById('parent_variant_id');
        parent.value = subId || mainId || '';
    }

    function loadSubCategories(mainId, selectedSubId) {
        var sub = document.getElementById('sub_category_id');
        var variantId = document.getElementById('variantId').value;
        sub.innerHTML = '';
        if (!mainId) {
            sub.innerHTML = '<option value="">Select Main Category first</option>';
            sub.disabled = true;
            syncParentVariantId();
            return;
        }
        sub.appendChild(new Option('None (use Main Category)', ''));
        variantsData.forEach(function (v) {
            if (String(v.parent_variant_id) === String(mainId) && String(variantId) !== String(v.id)) {
                sub.appendChild(new Option(v.name, v.id));
            }
        });
        sub.disabled = false;
        if (selectedSubId) sub.value = String(selectedSubId);
        syncParentVariantId();
    }

    function setMainCategory(id, name) {
        document.getElementById('main_category_id').value = id || '';
        document.getElementById('main_category_label').value = name || 'None (Create as Main category)';
        loadSubCategories(id || '', null);
    }

    function resetVariantForm() {
        clearVariantErrors();
        document.getElementById('variantId').value = '';
        document.getElementById('name').value = '';
        document.getElementById('v_status').value = 'active';
        setMainCategory('', 'None (Create as Main category)');
        document.getElementById('variantModalLabel').textContent = 'Add Variant';
    }

    function openVariantModal(title) {
        document.getElementById('variantModalLabel').textContent = title || 'Add/Edit Variant';
        showBsModal(document.getElementById('variantModal'));
    }

    function applyHierarchyFromParent(parentId) {
        if (!parentId) {
            setMainCategory('', 'None (Create as Main category)');
            return;
        }
        var parent = findVariantById(parentId);
        if (!parent) {
            setMainCategory(parentId, 'Variant #' + parentId);
            return;
        }
        if (!parent.parent_variant_id) {
            setMainCategory(parent.id, parent.name);
            return;
        }
        var main = findVariantById(parent.parent_variant_id);
        setMainCategory(
            parent.parent_variant_id,
            main ? main.name : ('Variant #' + parent.parent_variant_id)
        );
        loadSubCategories(parent.parent_variant_id, parent.id);
    }

    function notify(type, msg) {
        if (typeof alertify !== 'undefined') {
            if (type === 'success') alertify.success(msg);
            else alertify.error(msg);
        } else {
            alert(msg);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var addBtn = document.getElementById('addNewVariantBtn');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                resetVariantForm();
                // data-bs-toggle also opens; keep JS open as fallback
                openVariantModal('Add Variant');
            });
        }

        var variantModal = document.getElementById('variantModal');
        if (variantModal) {
            variantModal.addEventListener('show.bs.modal', function () {
                if (!document.getElementById('variantId').value) {
                    document.getElementById('variantModalLabel').textContent = 'Add Variant';
                }
            });
        }

        var chooseBtn = document.getElementById('chooseMainCategoryBtn');
        if (chooseBtn) {
            chooseBtn.addEventListener('click', function () {
                showBsModal(document.getElementById('mainCategoryModal'));
            });
        }

        document.addEventListener('click', function (e) {
            var mainOpt = e.target.closest('.main-category-option');
            if (mainOpt) {
                setMainCategory(mainOpt.getAttribute('data-id') || '', mainOpt.getAttribute('data-name') || '');
                hideBsModal(document.getElementById('mainCategoryModal'));
                return;
            }

            var editBtn = e.target.closest('.edit-variant');
            if (editBtn) {
                e.preventDefault();
                e.stopPropagation();
                var id = editBtn.getAttribute('data-id');
                clearVariantErrors();
                var spinner = document.getElementById('variantSpinner');
                if (spinner) spinner.style.display = 'inline-block';

                fetch(editUrlBase + '/' + id + '/edit', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (!r.ok) throw new Error('Failed to load variant.');
                    return r.json();
                }).then(function (variant) {
                    document.getElementById('variantId').value = variant.id;
                    document.getElementById('name').value = variant.name || '';
                    document.getElementById('v_status').value = variant.status || 'active';
                    applyHierarchyFromParent(variant.parent_variant_id || null);
                    openVariantModal('Edit Variant');
                }).catch(function (err) {
                    notify('error', err.message || 'Failed to load variant.');
                }).finally(function () {
                    if (spinner) spinner.style.display = 'none';
                });
                return;
            }

            var delBtn = e.target.closest('.delete-variant');
            if (delBtn) {
                e.preventDefault();
                var delId = delBtn.getAttribute('data-id');
                var doDelete = function () {
                    var body = new FormData();
                    body.append('_token', csrfToken);
                    body.append('id', delId);
                    body.append('status', 'deleted');
                    fetch(saveUrl, {
                        method: 'POST',
                        body: body,
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    }).then(function (r) { return r.json(); }).then(function (res) {
                        if (res.success) {
                            notify('success', res.message || 'Deleted.');
                            setTimeout(function () { window.location.reload(); }, 500);
                        } else {
                            notify('error', res.message || 'Delete failed.');
                        }
                    }).catch(function () {
                        notify('error', 'Delete failed.');
                    });
                };
                if (window.Swal) {
                    Swal.fire({
                        title: 'Delete variant?',
                        text: 'This cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete'
                    }).then(function (result) {
                        if (result.isConfirmed) doDelete();
                    });
                } else if (confirm('Delete this variant?')) {
                    doDelete();
                }
            }
        });

        var subSelect = document.getElementById('sub_category_id');
        if (subSelect) subSelect.addEventListener('change', syncParentVariantId);

        var form = document.getElementById('variantForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                clearVariantErrors();
                syncParentVariantId();

                var btn = document.getElementById('saveVariantBtn');
                var spinner = document.getElementById('variantSpinner');
                if (btn) btn.disabled = true;
                if (spinner) spinner.style.display = 'inline-block';

                var body = new FormData(form);
                if (!document.getElementById('variantId').value) {
                    body.set('id', '');
                }

                fetch(saveUrl, {
                    method: 'POST',
                    body: body,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (res.error) {
                        if (res.name) document.getElementById('error-name').textContent = res.name;
                        if (res.parent_variant_id) document.getElementById('error-parent_variant_id').textContent = res.parent_variant_id;
                        if (res.v_status) document.getElementById('error-v_status').textContent = res.v_status;
                        if (res.provider_id) notify('error', res.provider_id);
                        return;
                    }
                    if (res.success) {
                        notify('success', res.message || 'Saved successfully.');
                        hideBsModal(document.getElementById('variantModal'));
                        setTimeout(function () { window.location.reload(); }, 600);
                    } else {
                        notify('error', res.message || 'Save failed.');
                    }
                }).catch(function () {
                    notify('error', 'Save failed.');
                }).finally(function () {
                    if (btn) btn.disabled = false;
                    if (spinner) spinner.style.display = 'none';
                });
            });
        }

        // Sortable (optional — must not break Add/Edit if jQuery UI fails)
        if (window.jQuery && jQuery.fn && typeof jQuery.fn.sortable === 'function') {
            try {
                function getVariantGroup(row) {
                    var rows = row;
                    if (!row.data('parent-id')) {
                        var mainId = row.data('variant-id');
                        row.nextAll('tr.variant-row').each(function () {
                            var child = jQuery(this);
                            if (child.data('parent-id') == mainId) {
                                rows = rows.add(child);
                            } else if (!child.data('parent-id')) {
                                return false;
                            }
                        });
                    }
                    return rows;
                }

                function updateVariantOrder() {
                    var orderPayload = [];
                    var mainIndex = 1;
                    var subIndex = 1;
                    var currentMain = null;
                    jQuery('#sortableVariants > tr.variant-row').each(function () {
                        var row = jQuery(this);
                        var id = row.data('variant-id');
                        var parentId = row.data('parent-id');
                        if (!parentId) {
                            currentMain = id;
                            subIndex = 1;
                            orderPayload.push({ id: id, sr_no: mainIndex, parent_variant_id: null });
                            mainIndex++;
                        } else {
                            orderPayload.push({ id: id, sr_no: subIndex, parent_variant_id: currentMain });
                            subIndex++;
                        }
                    });

                    jQuery.ajax({
                        url: updateOrderUrl,
                        type: 'POST',
                        data: { _token: csrfToken, variants: orderPayload },
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) notify('success', 'Order updated successfully');
                            else notify('error', res.message || 'Order update failed');
                        },
                        error: function () {
                            notify('error', 'Order update failed');
                        }
                    });
                }

                jQuery('#sortableVariants').sortable({
                    handle: '.handle',
                    items: '> tr.variant-row',
                    axis: 'y',
                    placeholder: 'sortable-placeholder',
                    helper: function (e, item) {
                        var group = getVariantGroup(item);
                        var helper = jQuery('<tbody />');
                        group.clone().appendTo(helper);
                        return jQuery("<table class='table table-bordered' />").append(helper);
                    },
                    start: function (e, ui) {
                        var group = getVariantGroup(ui.item);
                        ui.item.data('group', group);
                        group.not(ui.item).hide();
                        var height = 0;
                        group.each(function () { height += jQuery(this).outerHeight(); });
                        ui.placeholder.height(height);
                    },
                    sort: function (e, ui) {
                        ui.placeholder.height(ui.helper.outerHeight());
                    },
                    stop: function (e, ui) {
                        var group = ui.item.data('group');
                        if (group && group.length > 1) {
                            var last = ui.item;
                            group.not(ui.item).each(function () {
                                jQuery(this).show();
                                last.after(jQuery(this));
                                last = jQuery(this);
                            });
                        }
                        jQuery('#sortableVariants').sortable('refresh');
                        updateVariantOrder();
                    }
                });
            } catch (err) {
                console.warn('Variant sortable init failed', err);
            }
        }
    });
})();
</script>

<style>
.sortable-placeholder {
    height: 50px;
    background: #f1f5f9;
    border: 2px dashed #94a3b8;
    visibility: visible !important;
}
</style>
@endpush