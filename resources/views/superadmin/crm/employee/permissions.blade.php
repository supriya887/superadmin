@extends('superadmin.layouts.crm')

@section('content')
<div class="crm-emp-shell">
	<div class="employee-permissions-layout">
		@include('superadmin.crm.partials.employee-navigation', ['active' => 'permissions'])

		<main class="employee-permissions-main">
			<header class="page-header employee-permissions-header">
				<div>
					<div class="breadcrumb welcome-text">
						<p>
							<a href="{{ Route::has('crm.dashboard') ? route('crm.dashboard') : '#' }}">Dashboard</a>
							<span>/</span>
							<a href="javascript:void(0)">Manage</a>
							<span>/</span>
							<a href="javascript:void(0)">Employee Management</a>
							<span>/</span>
							Employee Permissions
						</p>
					</div>
					<h2>Employee Permissions</h2>
				</div>
				<div class="back">
					<a class=" back-btn" href="{{ route('crm.employees') }}">
						<i data-lucide="arrow-left"></i><span>Back</span>
					</a>
				</div>
			</header>

			@if ($selectedEmployee)
			<div class="common-table-card employee-permissions-profile">
				@if ($selectedEmployee->avatar_url)
				<img src="{{ $selectedEmployee->avatar_url }}" alt="{{ $selectedEmployee->name }}" class="employee-permissions-profile-image">
				@else
				<span class="employee-permissions-profile-initials" aria-hidden="true">
					{{ strtoupper(substr(trim($selectedEmployee->name), 0, 1)) ?: 'E' }}
				</span>
				@endif
				<div class="employee-permissions-profile-details">
					<strong>{{ $selectedEmployee->name }}</strong>
					<span>{{ ucwords(str_replace('_', ' ', $selectedRole)) }}@if ($selectedEmployee->email) · {{ $selectedEmployee->email }}@endif</span>
				</div>
			</div>
			@endif

			@if (session('success'))
			<p class="employee-permissions-alert" role="status">{{ session('success') }}</p>
			@endif

			<section class="employee-permissions-panel" aria-label="Employee permission settings">
				@if ($roles->isEmpty())
				<div class="employee-permissions-empty">
					<p>No employee roles are available to configure.</p>
					<a href="{{ route('crm.employee.create') }}">Add Employee</a>
				</div>
				@else
				@php
				$permissionActions = [
				'view' => 'View',
				'add' => 'Add',
				'edit' => 'Edit',
				'delete' => 'Delete',
				];
				@endphp

				<form method="GET" action="{{ route('crm.employee.permissions') }}" class="employee-permissions-toolbar">
					<label for="role-selector" class="permission-scope-field">Role defaults
						<select id="role-selector" name="role" onchange="this.form.elements['employee_id'].value = ''; this.form.submit()">
							@foreach ($roles as $roleOption)
							<option value="{{ $roleOption }}" @selected($selectedRole===$roleOption)>
								{{ ucwords(str_replace('_', ' ', $roleOption)) }}
							</option>
							@endforeach
						</select>
					</label>
					<label for="employee-selector" class="permission-scope-field">Employee override
						<select id="employee-selector" name="employee_id" onchange="this.form.submit()">
							<option value="">Use role defaults</option>
							@foreach ($employees as $employeeOption)
							<option value="{{ $employeeOption->id }}" @selected($selectedEmployee && $selectedEmployee->id === $employeeOption->id)>
								{{ $employeeOption->name }} ({{ ucwords(str_replace('_', ' ', $employeeOption->role ?? 'user')) }})
							</option>
							@endforeach
						</select>
					</label>
					<label class="permission-select-control permission-check">
						<input type="checkbox" data-select-all aria-label="Select all permissions">
						<span aria-hidden="true"></span>
						Select All
					</label>
				</form>
				<div class="employee-permissions-scope-note">
					@if ($selectedEmployee)
					@if ($hasEmployeeOverride)
					<strong>{{ $selectedEmployee->name }}</strong> has individual permission overrides.
					@else
					<strong>{{ $selectedEmployee->name }}</strong> currently inherits {{ ucwords(str_replace('_', ' ', $selectedRole)) }} permissions.
					@endif
					@else
					These defaults apply to all {{ ucwords(str_replace('_', ' ', $selectedRole)) }} employees without an individual override.
					@endif
				</div>

				<form method="POST" action="{{ route('crm.employee.permissions.save') }}">
					@csrf
					<input type="hidden" name="role" value="{{ $selectedRole }}">
					@if ($selectedEmployee)
					<input type="hidden" name="employee_id" value="{{ $selectedEmployee->id }}">
					@endif
					<div class="employee-permissions-table-wrap">
						<table class="employee-permissions-table">
							<thead>
								<tr>
									<th scope="col">Module</th>
									@foreach ($permissionActions as $label)
									<th scope="col">{{ $label }}</th>
									@endforeach
								</tr>
							</thead>
							@foreach ($modules as $moduleKey => $module)
							@php
							$moduleLabel = data_get($module, 'label');
							$moduleItems = data_get($module, 'items', []);
							@endphp
							<tbody data-permission-section>
								<tr class="permission-group">
									<th colspan="{{ count($permissionActions) + 1 }}" scope="rowgroup">
										<div class="employee-permissions-group-head">
											<label class="permission-select-control permission-check">
												<input type="checkbox" data-select-section aria-label="Select all {{ $moduleLabel }} permissions">
												<span aria-hidden="true"></span>

											</label>
											<span class="permission-group-label">{{ $moduleLabel }}</span>
										</div>
									</th>
								</tr>
								@foreach ($moduleItems as $itemKey => $itemLabel)
								<tr class="permission-row">
									<td><span class="permission-item">{{ $itemLabel }}</span></td>
									@foreach ($permissionActions as $action => $label)
									<td class="permission-check-cell">
										<label class="permission-check">
											<input type="checkbox" data-permission-input
												name="permissions[{{ $moduleKey }}][{{ $itemKey }}][{{ $action }}]"
												value="1"
												aria-label="{{ $itemLabel }}: {{ $label }}"
												@checked($permissions[$moduleKey][$itemKey][$action] ?? false)>
											<span aria-hidden="true"></span>
										</label>
									</td>
									@endforeach
								</tr>
								@endforeach
							</tbody>
							@endforeach
						</table>
					</div>
					<div class="employee-permissions-actions">
						@if ($selectedEmployee && $hasEmployeeOverride)
						<button type="submit" name="reset_employee_override" value="1" class="employee-permissions-reset">
							Use Role Defaults
						</button>
						@endif
						<button type="submit" class="employee-permissions-save">
							<i data-lucide="save" aria-hidden="true"></i><span>{{ $selectedEmployee ? 'Save Employee Permissions' : 'Save Role Defaults' }}</span>
						</button>
					</div>
				</form>
				@endif
			</section>
		</main>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function() {
		if (typeof lucide !== 'undefined') {
			lucide.createIcons();
		}

		const form = document.querySelector('.employee-permissions-main form[method="POST"]');
		if (!form) return;

		const permissionInputs = Array.from(form.querySelectorAll('[data-permission-input]'));
		const selectAll = document.querySelector('[data-select-all]');
		const sections = Array.from(form.querySelectorAll('[data-permission-section]'));

		const syncSelectControl = (control, inputs) => {
			const checkedCount = inputs.filter(input => input.checked).length;
			control.checked = inputs.length > 0 && checkedCount === inputs.length;
			control.indeterminate = checkedCount > 0 && checkedCount < inputs.length;
		};

		const syncSelectAll = () => {
			syncSelectControl(selectAll, permissionInputs);
			sections.forEach(section => {
				syncSelectControl(
					section.querySelector('[data-select-section]'),
					Array.from(section.querySelectorAll('[data-permission-input]'))
				);
			});
		};

		selectAll.addEventListener('change', function() {
			permissionInputs.forEach(input => input.checked = selectAll.checked);
			syncSelectAll();
		});

		sections.forEach(section => {
			const sectionControl = section.querySelector('[data-select-section]');
			sectionControl.addEventListener('change', function() {
				section.querySelectorAll('[data-permission-input]').forEach(input => input.checked = sectionControl.checked);
				syncSelectAll();
			});
		});

		form.addEventListener('change', function(event) {
			if (event.target.matches('[data-permission-input]')) syncSelectAll();
		});

		syncSelectAll();
	});
</script>
@endsection
