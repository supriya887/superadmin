@extends('superadmin.layouts.crm')

@section('content')
    @php
        $items = $priorities ?? ($leadPriorities ?? collect());
        $totalCount = $totalPriorities ?? (method_exists($items, 'total') ? $items->total() : count($items));
        $leadsMap = $leadsCountMap ?? ['Hot' => 86, 'Normal' => 245, 'Cold' => 132];
    @endphp

    
    @include('superadmin.crm.crmsetting.partials.sidebar')
    <!-- Modal: Add Lead Priority -->
    <div id="addModal"
        style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 380px; padding: 26px 28px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); position: relative;">
            
            <!-- Close Button -->
            <button type="button" onclick="closeAddModal()"
                style="position: absolute; top: 16px; right: 16px; border: none; background: transparent; cursor: pointer; font-size: 20px; color: #334155; line-height: 1; padding: 2px;">
                &times;
            </button>

            <!-- Title -->
            <h3 style="margin: 0 0 22px 0; font-size: 18px; font-weight: 700; color: #1e293b; text-align: center;">
                Add Lead Priorityy
            </h3>

            <form method="POST" action="{{ route('crm.lead-priority.store') }}">
                @csrf

                <!-- Priority Name -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Priority Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" placeholder="e.g. Hot, Normal, Cold" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; color: #1e293b;">
                </div>

                <!-- Description -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Description <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="description" placeholder="Enter description"
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; color: #1e293b;">
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

    <!-- Modal: Edit Lead Priority -->
    <div id="editModal"
        style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 380px; padding: 26px 28px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); position: relative;">
            
            <!-- Close Button -->
            <button type="button" onclick="closeEditModal()"
                style="position: absolute; top: 16px; right: 16px; border: none; background: transparent; cursor: pointer; font-size: 20px; color: #334155; line-height: 1; padding: 2px;">
                &times;
            </button>

            <!-- Title -->
            <h3 style="margin: 0 0 22px 0; font-size: 18px; font-weight: 700; color: #1e293b; text-align: center;">
                Edit Lead Priority
            </h3>

            <form id="editForm" method="POST" action="">
                @csrf

                <!-- Priority Name -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Priority Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="editNameInput" name="name" placeholder="e.g. Hot, Normal, Cold" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; color: #1e293b;">
                </div>

                <!-- Description -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Description <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="editDescInput" name="description" placeholder="Enter description"
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; color: #1e293b;">
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
            if (modal) modal.style.display = 'flex';
        }

        function closeAddModal() {
            const modal = document.getElementById('addModal');
            if (modal) modal.style.display = 'none';
        }

        function openEditModal(id, name, description) {
            const modal = document.getElementById('editModal');
            const form = document.getElementById('editForm');
            const nameInput = document.getElementById('editNameInput');
            const descInput = document.getElementById('editDescInput');

            if (modal && form) {
                form.action = "{{ url('crm/lead-priority') }}/" + id + "/update";
                if (nameInput) nameInput.value = name;
                if (descInput) descInput.value = description || '';
                modal.style.display = 'flex';
            }
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            if (modal) modal.style.display = 'none';
        }

        window.addEventListener('click', function(e) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (e.target === addModal) closeAddModal();
            if (e.target === editModal) closeEditModal();
        });

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
@endsection
