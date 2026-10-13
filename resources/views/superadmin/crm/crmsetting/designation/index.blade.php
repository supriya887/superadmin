@extends('superadmin.layouts.crm')

@section('content')
    @php
        $items = $designations ?? collect();
        $totalCount = $totalDesignations ?? count($items);
        $employeesCount = $totalEmployees ?? 48;
        $commonDesignation = $mostCommonDesignation ?? 'Sales Executive';
        $mgmtDesignations = $managementDesignations ?? 4;
    @endphp

   
@include('superadmin.crm.crmsetting.partials.sidebar')
    <!-- Modal: Add Designation -->
    <div id="addModal"
        style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 420px; padding: 26px 28px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); position: relative;">
            
            <!-- Close Button -->
            <button type="button" onclick="closeAddModal()"
                style="position: absolute; top: 16px; right: 16px; border: none; background: transparent; cursor: pointer; font-size: 20px; color: #334155; line-height: 1; padding: 2px;">
                &times;
            </button>

            <!-- Title -->
            <h3 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 700; color: #1e293b; text-align: center;">
                Add Designation
            </h3>

            <form method="POST" action="{{ route('crm.designation.store') }}">
                @csrf

                <!-- Designation Name -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Designation Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" placeholder="e.g. Sales Executive" required
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 13px; box-sizing: border-box; outline: none;">
                </div>

                <!-- Department -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Department
                    </label>
                    <input type="text" name="department" placeholder="e.g. Sales, Accounts, Management"
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 13px; box-sizing: border-box; outline: none;">
                </div>

                <!-- Employee Count -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Employee Count
                    </label>
                    <input type="number" name="employee_count" placeholder="0" min="0" value="0"
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 13px; box-sizing: border-box; outline: none;">
                </div>

                <!-- Description -->
                <div style="margin-bottom: 22px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Description
                    </label>
                    <textarea name="description" placeholder="Optional description..." rows="2"
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 13px; box-sizing: border-box; outline: none;"></textarea>
                </div>

                <!-- Submit Button -->
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeAddModal()"
                        style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                        style="background: #0056b3; color: #ffffff; border: none; padding: 9px 22px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Designation -->
    <div id="editModal"
        style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 420px; padding: 26px 28px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); position: relative;">
            
            <!-- Close Button -->
            <button type="button" onclick="closeEditModal()"
                style="position: absolute; top: 16px; right: 16px; border: none; background: transparent; cursor: pointer; font-size: 20px; color: #334155; line-height: 1; padding: 2px;">
                &times;
            </button>

            <!-- Title -->
            <h3 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 700; color: #1e293b; text-align: center;">
                Edit Designation
            </h3>

            <form id="editForm" method="POST" action="">
                @csrf

                <!-- Designation Name -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Designation Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="edit_name" name="name" required
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 13px; box-sizing: border-box; outline: none;">
                </div>

                <!-- Department -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Department
                    </label>
                    <input type="text" id="edit_department" name="department"
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 13px; box-sizing: border-box; outline: none;">
                </div>

                <!-- Employee Count -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Employee Count
                    </label>
                    <input type="number" id="edit_employee_count" name="employee_count" min="0"
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 13px; box-sizing: border-box; outline: none;">
                </div>

                <!-- Description -->
                <div style="margin-bottom: 22px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Description
                    </label>
                    <textarea id="edit_description" name="description" rows="2"
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 13px; box-sizing: border-box; outline: none;"></textarea>
                </div>

                <!-- Submit Button -->
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeEditModal()"
                        style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                        style="background: #0056b3; color: #ffffff; border: none; padding: 9px 22px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script for Modal Handling -->
    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(id, name, department, employeeCount, description) {
            const form = document.getElementById('editForm');
            form.action = "{{ url('crm/designation') }}/" + id + "/update";
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_department').value = department;
            document.getElementById('edit_employee_count').value = employeeCount;
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === editModal) {
                closeEditModal();
            }
        }
    </script>
@endsection
