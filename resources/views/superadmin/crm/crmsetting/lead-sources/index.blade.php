@extends('superadmin.layouts.crm')

@section('content')
    @php
        $items = $leadSources ?? ($sources ?? collect());
        $totalSourcesCount = $totalSources ?? (method_exists($items, 'total') ? $items->total() : $items->count());
        $totalLeadsCount = $totalLeads ?? 788;
        $topSourceName = $topLeadSource ?? 'Website';
        $convRate = $conversionRate ?? '24.6%';
        $monthLeads = $newLeadsThisMonth ?? 126;
    @endphp
@include('superadmin.crm.crmsetting.partials.sidebar')
    

    <!-- Modal: Add Lead Source (Exact design from screenshot) -->
    <div id="addModal"
        style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 390px; padding: 26px 28px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); position: relative;">
            
            <!-- Close Button -->
            <button type="button" onclick="closeAddModal()"
                style="position: absolute; top: 16px; right: 16px; border: none; background: transparent; cursor: pointer; font-size: 20px; color: #334155; line-height: 1; padding: 2px;">
                &times;
            </button>

            <!-- Title -->
            <h3 style="margin: 0 0 22px 0; font-size: 18px; font-weight: 700; color: #1e293b; text-align: center;">
                Add Lead Source
            </h3>

            <form method="POST" action="{{ route('crm.lead-sources.store') }}" enctype="multipart/form-data">
                @csrf

                <!-- Logo -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Logo <span style="color: #ef4444;">*</span>
                    </label>
                    <div style="display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff;">
                        <span id="addFileName"
                            style="flex: 1; padding: 9px 12px; font-size: 12.5px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            Choose File
                        </span>
                        <label for="addLogoInput"
                            style="padding: 9px 16px; background: #fff; border-left: 1px solid #e2e8f0; font-size: 12.5px; font-weight: 500; color: #334155; cursor: pointer; margin: 0; white-space: nowrap;">
                            Choose File
                        </label>
                        <input type="file" id="addLogoInput" name="logo" accept="image/*" required style="display: none;"
                            onchange="document.getElementById('addFileName').innerText = this.files[0] ? this.files[0].name : 'Choose File'">
                    </div>
                </div>

                <!-- Source Name -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Source Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" placeholder="Enter lead source name" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; color: #1e293b;">
                </div>

                <!-- Status -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Status <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="status" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; background: #fff; color: #1e293b;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <!-- Save Button -->
                <div style="text-align: center;">
                    <button type="submit"
                        style="min-width: 180px; background: #0056b3; color: #ffffff; border: none; padding: 11px 36px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 86, 179, 0.2);">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Lead Source -->
    <div id="editModal"
        style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 390px; padding: 26px 28px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); position: relative;">
            
            <!-- Close Button -->
            <button type="button" onclick="closeEditModal()"
                style="position: absolute; top: 16px; right: 16px; border: none; background: transparent; cursor: pointer; font-size: 20px; color: #334155; line-height: 1; padding: 2px;">
                &times;
            </button>

            <!-- Title -->
            <h3 style="margin: 0 0 22px 0; font-size: 18px; font-weight: 700; color: #1e293b; text-align: center;">
                Edit Lead Source
            </h3>

            <form id="editForm" method="POST" action="" enctype="multipart/form-data">
                @csrf

                <!-- Logo -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Logo
                    </label>
                    <div style="display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff;">
                        <span id="editFileName"
                            style="flex: 1; padding: 9px 12px; font-size: 12.5px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            Choose File
                        </span>
                        <label for="editLogoInput"
                            style="padding: 9px 16px; background: #fff; border-left: 1px solid #e2e8f0; font-size: 12.5px; font-weight: 500; color: #334155; cursor: pointer; margin: 0; white-space: nowrap;">
                            Choose File
                        </label>
                        <input type="file" id="editLogoInput" name="logo" accept="image/*" style="display: none;"
                            onchange="document.getElementById('editFileName').innerText = this.files[0] ? this.files[0].name : 'Choose File'">
                    </div>
                </div>

                <!-- Source Name -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Source Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="editNameInput" name="name" placeholder="Enter lead source name" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; color: #1e293b;">
                </div>

                <!-- Status -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Status <span style="color: #ef4444;">*</span>
                    </label>
                    <select id="editStatusInput" name="status" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; background: #fff; color: #1e293b;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <!-- Save Button -->
                <div style="text-align: center;">
                    <button type="submit"
                        style="min-width: 180px; background: #0056b3; color: #ffffff; border: none; padding: 11px 36px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 86, 179, 0.2);">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            const modal = document.getElementById('addModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeAddModal() {
            const modal = document.getElementById('addModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function openEditModal(id, name, status, logoUrl) {
            const modal = document.getElementById('editModal');
            const form = document.getElementById('editForm');
            const nameInput = document.getElementById('editNameInput');
            const statusInput = document.getElementById('editStatusInput');
            const fileName = document.getElementById('editFileName');

            if (modal && form) {
                form.action = "{{ url('crm/lead-sources') }}/" + id + "/update";
                if (nameInput) nameInput.value = name;
                if (statusInput) statusInput.value = status ? status.toLowerCase() : 'active';
                if (fileName) fileName.innerText = logoUrl ? 'Current logo selected' : 'Choose File';
                modal.style.display = 'flex';
            }
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        window.addEventListener('click', function(e) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (e.target === addModal) {
                closeAddModal();
            }
            if (e.target === editModal) {
                closeEditModal();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
@endsection
