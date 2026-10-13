<div class="vertical-menu">

    <div data-simplebar class="h-100">

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">
                <li>
                    <a href="{{ url('dashboard') }}">
                        <i class="mdi mdi-home-outline"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0);" class="has-arrow">
                        <i class="dripicons-user-id"></i>
                        <span>Admin</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ url('usercreation-list') }}"class="user_creation"> User Creation</a></li>
                        <li>
                            <a href="javascript:void(0);" class="has-arrow">
                               <span>Authority</span>
                            </a>
                            <ul class="sub-menu" aria-expanded="false">
                                <li><a href="{{ url('authority-table') }}"class="authority_table"> Authority Table</a></li>
                                <li><a href="{{ url('rolepermission-table') }}"class="authority_table"> Module List </a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li>
                    <a href="javascript:void(0);" class="has-arrow">
                        <i class="dripicons-user-id"></i>
                        <span>Logistics Master</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ url('airline') }}" class="airline">Airline</a></li>
                        <li><a href="{{ url('airport') }}" class="airport">Airport</a></li>
                        <li><a href="{{ url('bondno') }}" class="bondno">Bond No</a></li>
                        <li><a href="{{ url('charges') }}" class="charges">Charges</a></li>
                        <li><a href="{{ url('container-agent') }}" class="container_agent">Container Agent</a></li>
                        <li><a href="{{ url('contract') }}" class="contract">Contract </a></li>
                        <li><a href="{{ url('currency') }}" class="currency">Currency </a></li>
                        <li><a href="{{ url('customer') }}" class="customer">Customer </a></li>
                        <li><a href="{{ url('depo') }}" class="depo">Depo </a></li>
                        <li><a href="{{ url('followup-type-table') }}" class="followup-type-table">Followup Type Table </a></li>
                        <li><a href="{{ url('logisticmstline-table') }}" class="logisticmstline-table">Line </a></li>
                        <li><a href="{{ url('lineagent-table') }}" class="lineagent-table">Line Agent</a></li>
                        <li><a href="{{ url('linerates-table') }}" class="linerates-table">Line Rates</a></li>
                        <li><a href="{{ url('port-table') }}" class="port-table">Port</a></li>
                        <li><a href="{{ url('size-table') }}" class="size-table">Size</a></li>
                        <li><a href="{{ url('terminal-table') }}" class="terminal-table">Terminal</a></li>
                        <li><a href="{{ url('unit-table') }}" class="unit-table">Unit</a></li>
                        <li><a href="{{ url('vendor-table') }}" class="vendor-table">Vendor</a></li>
                        <li><a href="{{ url('vessel-table') }}" class="vessel-table">Vessel</a></li>
                    </ul>
                </li>

                {{-- <li>
                    <a href="javascript:void(0);" class="has-arrow">
                        <i class="dripicons-user-id"></i>
                        <span>Transaction</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ url('order-transaction/1') }}" class="Ordered">Ordered</a></li>
                        <li><a href="{{ url('order-transaction/2') }}" class="Ondrying">Ondrying</a></li>
                        <li><a href="{{ url('order-transaction/3') }}" class="Finished">Finished</a></li>
                        <li><a href="{{ url('order-transaction/4') }}" class="Delivered">Delivered</a></li>
                    </ul>
                </li> --}}
            </ul>
        </div>
        <!-- Sidebar -->
    </div>
</div>
