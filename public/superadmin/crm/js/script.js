if (window.lucide) {
    lucide.createIcons();
}

// sidebar active
document.addEventListener("DOMContentLoaded", function () {

    const currentPage = window.location.pathname.split("/").pop();

    // =========================================
    // CURRENT PAGE ACTIVE
    // =========================================

    document.querySelectorAll(".menu a[href]").forEach(link => {

        const href = link.getAttribute("href");

        if (href === currentPage) {

            const currentLi = link.closest("li");

            if (currentLi) {
                currentLi.classList.add("active");
            }


            const parentLi = link.closest(".has-sub");

            if (parentLi) {

                parentLi.classList.add("active");
                parentLi.classList.add("open");

                const subMenu = parentLi.querySelector(".sub-menu");

                if (subMenu) {
                    subMenu.style.maxHeight =
                        subMenu.scrollHeight + "px";
                }
            }
        }
    });


    // =========================================
    // SIDEBAR TOGGLE + FULL CONTENT
    // =========================================

    const toggle = document.querySelector(".sidebar-toggle");
    const sidebar = document.querySelector(".sidebar");
    const mainContent = document.querySelector("main.content");

    if (toggle && sidebar) {

        toggle.addEventListener("click", () => {

            // Sidebar close/open
            sidebar.classList.toggle("close");


            // Main content full width
            if (mainContent) {

                if (sidebar.classList.contains("close")) {

                    mainContent.classList.add("full-content");

                } else {

                    mainContent.classList.remove("full-content");

                }

            }

        });
    }


// =========================================
// SUBMENU HOVER OPEN CLASS
// =========================================

document.querySelectorAll(".has-sub").forEach(item => {

    item.addEventListener("mouseenter", function () {

        // Hover par open class
        this.classList.add("open");

        const subMenu = this.querySelector(".sub-menu");

        if (subMenu) {
            subMenu.style.maxHeight = subMenu.scrollHeight + "px";
        }

    });


    item.addEventListener("mouseleave", function () {

        // Agar active/current page nahi hai to open remove karo
        if (!this.classList.contains("active")) {

            this.classList.remove("open");

            const subMenu = this.querySelector(".sub-menu");

            if (subMenu) {
                subMenu.style.maxHeight = null;
            }

        }

    });

});

});
// notification and profile
document.addEventListener("DOMContentLoaded", function () {

    lucide.createIcons();

    const notificationBtn =
        document.getElementById("notificationBtn");

    const notificationSidebar =
        document.getElementById("notificationSidebar");

    const notificationOverlay =
        document.getElementById("notificationOverlay");

    const notificationClose =
        document.getElementById("notificationClose");


    const profileBtn =
        document.getElementById("profileBtn");

    const profileDropdown =
        document.getElementById("profileDropdown");


    /*
    ========================================
        Open Notification Sidebar
    ========================================
    */

    notificationBtn.addEventListener("click", function (e) {

        e.stopPropagation();

        notificationSidebar.classList.add("show");
        notificationOverlay.classList.add("show");

        // Profile dropdown close
        profileDropdown.classList.remove("show");

        // Prevent page scroll
        document.body.style.overflow = "hidden";

    });


    /*
    ========================================
        Close Notification Sidebar
    ========================================
    */

    notificationClose.addEventListener("click", function () {

        notificationSidebar.classList.remove("show");
        notificationOverlay.classList.remove("show");

        document.body.style.overflow = "";

    });


    /*
    ========================================
        Close on Overlay Click
    ========================================
    */

    notificationOverlay.addEventListener("click", function () {

        notificationSidebar.classList.remove("show");
        notificationOverlay.classList.remove("show");

        document.body.style.overflow = "";

    });


    /*
    ========================================
        Profile Toggle
    ========================================
    */

    profileBtn.addEventListener("click", function (e) {

        e.stopPropagation();

        profileDropdown.classList.toggle("show");

        // Notification sidebar close
        notificationSidebar.classList.remove("show");
        notificationOverlay.classList.remove("show");

        document.body.style.overflow = "";

    });


    /*
    ========================================
        Profile Dropdown Click
    ========================================
    */

    profileDropdown.addEventListener("click", function (e) {

        e.stopPropagation();

    });


    /*
    ========================================
        ESC Key
    ========================================
    */

    document.addEventListener("keydown", function (e) {

        if (e.key === "Escape") {

            notificationSidebar.classList.remove("show");
            notificationOverlay.classList.remove("show");

            profileDropdown.classList.remove("show");

            document.body.style.overflow = "";

        }

    });

});

// b2b-b2c switch
document.querySelectorAll(".user-type-switch button").forEach(btn => {
    btn.addEventListener("click", function () {
        const active = document.querySelector(".user-type-switch .active");
        if (active) active.classList.remove("active");
        this.classList.add("active");
    });
});

// sales filter
document.querySelectorAll(".sales-filter button").forEach(button => {
    button.addEventListener("click", function () {
        const active = document.querySelector(".sales-filter .active");
        if (active) active.classList.remove("active");
        this.classList.add("active");
    });
});

// chart
const ctx = document.getElementById("salesChart");

if (ctx) {
    new Chart(ctx, {
        type: "line",
        data: {
            labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
            datasets: [{
                label: "Sales",
                data: [500,1000,800,1400,2200,2600,2100,2800,2600,3400,3600,4000],
                borderColor: "#0355ac",
                backgroundColor: "rgba(3,85,172,.08)",
                fill: true,
                tension: .4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });
}

// date filter
const dateSort = document.getElementById("dateSort");
const tableBody = document.querySelector(".table-body");
const dateSortIcon = document.getElementById("dateSortIcon");

if (dateSort && tableBody && dateSortIcon) {
    let ascending = true;

    dateSort.addEventListener("click", () => {
        const rows = Array.from(tableBody.querySelectorAll(".approval-row"));

        rows.sort((a, b) => {
            const dateA = new Date(a.querySelector(".approval-date").textContent.trim().replace(",", ""));
            const dateB = new Date(b.querySelector(".approval-date").textContent.trim().replace(",", ""));
            return ascending ? dateA - dateB : dateB - dateA;
        });

        rows.forEach(row => tableBody.appendChild(row));

        dateSortIcon.className = ascending ? "arrow-up-line" : "arrow-down-line";
        ascending = !ascending;
    });
}

// filter
document.addEventListener("DOMContentLoaded", function () {

    const filterWraps = document.querySelectorAll(".table-filter-wrap");

    filterWraps.forEach(function (filterPanel) {

        const filterArea = filterPanel.parentElement;
        const toggleBtn = filterArea.querySelector("#toggleFilter") || filterArea.querySelector(".filter-btn");

        if (!toggleBtn) return;

        function updateFilterButton() {
            const icon = toggleBtn.querySelector("svg");
            const text = toggleBtn.querySelector("span");
            const resetBtn = filterArea.querySelector("#resetFilters");

            if (filterPanel.classList.contains("active")) {

                toggleBtn.classList.add("is-open");

                if (icon) {
                    icon.outerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M9 3L3 9M3 3L9 9"
                              stroke="white"
                              stroke-width="1.5"
                              stroke-linecap="round"
                              stroke-linejoin="round"/>
                    </svg>`;
                }

                if (text) text.textContent = "Close";
                if (resetBtn) resetBtn.style.display = "inline-flex";

            } else {

                toggleBtn.classList.remove("is-open");

                if (icon) {
                    icon.outerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="9" viewBox="0 0 12 9" fill="none">
                        <path d="M0.75 0.75H10.75M2.75 4.25H8.75M4.25 7.75H7.25"
                              stroke="white"
                              stroke-width="1.5"
                              stroke-linecap="round"
                              stroke-linejoin="round"/>
                    </svg>`;
                }

                if (text) text.textContent = "Filters";
                if (resetBtn) resetBtn.style.display = "none";
            }
        }

        toggleBtn.addEventListener("click", function (e) {
            e.stopPropagation();

            filterWraps.forEach(function (otherPanel) {
                if (otherPanel !== filterPanel) {
                    otherPanel.classList.remove("active");

                    const otherBtn = otherPanel.parentElement.querySelector("#toggleFilter") || otherPanel.parentElement.querySelector(".filter-btn");
                    const otherResetBtn = otherPanel.parentElement.querySelector("#resetFilters");
                    if (otherResetBtn) {
                        otherResetBtn.style.display = "none";
                    }
                    if (otherBtn) {
                        otherBtn.classList.remove("is-open");
                        const otherText = otherBtn.querySelector("span");
                        if (otherText) otherText.textContent = "Filters";
                        const otherIcon = otherBtn.querySelector("svg");
                        if (otherIcon) {
                            otherIcon.outerHTML = `
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="9" viewBox="0 0 12 9" fill="none">
                                <path d="M0.75 0.75H10.75M2.75 4.25H8.75M4.25 7.75H7.25"
                                      stroke="white"
                                      stroke-width="1.5"
                                      stroke-linecap="round"
                                      stroke-linejoin="round"/>
                            </svg>`;
                        }
                    }
                }
            });

            filterPanel.classList.toggle("active");
            updateFilterButton();

            if (!filterPanel.classList.contains("active")) {
                try {
                    const url = new URL(window.location.href);
                    if (url.searchParams.has('filter_open')) {
                        url.searchParams.delete('filter_open');
                        window.history.replaceState({}, '', url.toString());
                    }
                } catch (err) {}
            }
        });

        updateFilterButton();

    });

});

// pop-up-form
// Common Modal Handler
document.addEventListener('DOMContentLoaded', function () {

    // Open Modal
    document.querySelectorAll('[data-modal-open]').forEach(function (openBtn) {

        openBtn.addEventListener('click', function (e) {
            e.preventDefault();

            const modalId = this.getAttribute('data-modal-open');
            const modal = document.getElementById(modalId);

            if (modal) {
                modal.classList.add('active');
            }

        });

    });


    // Close Modal - Close Buttons
    document.querySelectorAll('[data-modal-close]').forEach(function (closeBtn) {

        closeBtn.addEventListener('click', function () {

            const modal = this.closest('.credit-modal');

            if (modal) {
                modal.classList.remove('active');
            }

        });

    });


    // Close Modal - Outside Click
    document.querySelectorAll('.credit-modal').forEach(function (modal) {

        modal.addEventListener('click', function (e) {

            if (e.target === modal) {
                modal.classList.remove('active');
            }

        });

    });


    // Close Modal - ESC
    document.addEventListener('keydown', function (e) {

        if (e.key === 'Escape') {

            document.querySelectorAll('.credit-modal.active').forEach(function (modal) {
                modal.classList.remove('active');
            });

        }

    });

});

// transaction tabs
document.addEventListener("DOMContentLoaded", function () {

    const tabs = document.querySelectorAll(".transaction-tabs .tab-btn");
    const rows = document.querySelectorAll(".transaction-list .transaction-row");
    const indicator = document.querySelector(".transaction-tabs .tab-indicator");

    if (!tabs.length || !rows.length || !indicator) return;

    function moveIndicator(button) {
        const parentRect = button.parentElement.getBoundingClientRect();
        const rect = button.getBoundingClientRect();

        indicator.style.width = rect.width + "px";
        indicator.style.transform = `translateX(${rect.left - parentRect.left}px)`;
    }

    function filterRows(type) {
        rows.forEach(row => {
            row.style.display =
                row.getAttribute("data-type") === type ? "grid" : "none";
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener("click", function () {

            tabs.forEach(btn => btn.classList.remove("active"));
            this.classList.add("active");

            moveIndicator(this);
            filterRows(this.dataset.filter);

        });
    });

    // Initial load
    const activeTab = document.querySelector(".transaction-tabs .tab-btn.active");
    moveIndicator(activeTab);
    filterRows(activeTab.dataset.filter);

    // Resize
    window.addEventListener("resize", function () {
        const active = document.querySelector(".transaction-tabs .tab-btn.active");
        if (active) moveIndicator(active);
    });

});
// Partner-approval
document.addEventListener("DOMContentLoaded", function () {

    const tabs = document.querySelectorAll(".partner-tab");
    const tableBody = document.querySelector(".table-body");
    const dateSort = document.getElementById("dateSort");
    const dateSortIcon = document.getElementById("dateSortIcon");

    // Common filter rows
    let allRows = Array.from(
        document.querySelectorAll(".filter-row")
    );


    // =========================
    // FILTER FUNCTION
    // =========================

    function filterRows(filterStatus) {

        allRows.forEach(row => {

            const rowStatus = row.getAttribute("data-status");

            if (
                filterStatus === "All" ||
                rowStatus === filterStatus
            ) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }

        });

    }


    // =========================
    // TAB CLICK
    // =========================

    tabs.forEach(tab => {

        tab.addEventListener("click", function () {

            // Active tab
            tabs.forEach(t => {
                t.classList.remove("active");
            });

            this.classList.add("active");


            // Directly get data-filter
            const filterStatus = this.getAttribute("data-filter");

            // Filter
            filterRows(filterStatus);

        });

    });


    // =========================
    // DEFAULT ACTIVE TAB
    // =========================

    const pendingTab = document.querySelector(
        '.partner-tab[data-filter="Pending"]'
    );

    if (pendingTab) {

        tabs.forEach(t => {
            t.classList.remove("active");
        });

        pendingTab.classList.add("active");

        filterRows("Pending");

    }


    // =========================
    // DATE SORTING
    // =========================

    let sortAscending = true;

    if (dateSort) {

        dateSort.addEventListener("click", function () {

            allRows.sort(function (a, b) {

                const dateElementA =
                    a.querySelector(".approval-date");

                const dateElementB =
                    b.querySelector(".approval-date");


                if (!dateElementA || !dateElementB) {
                    return 0;
                }


                const dateA = parseDate(
                    dateElementA.textContent.trim()
                );

                const dateB = parseDate(
                    dateElementB.textContent.trim()
                );


                return sortAscending
                    ? dateA - dateB
                    : dateB - dateA;

            });


            // Reorder rows
            if (tableBody) {

                allRows.forEach(row => {
                    tableBody.appendChild(row);
                });

            }


            // Change icon
            if (dateSortIcon) {

                dateSortIcon.setAttribute(
                    "data-lucide",
                    sortAscending
                        ? "arrow-up"
                        : "arrow-down"
                );


                if (typeof lucide !== "undefined") {
                    lucide.createIcons();
                }

            }


            sortAscending = !sortAscending;

        });

    }


    // =========================
    // DATE CONVERT
    // =========================

    function parseDate(dateString) {

        const parts = dateString.split(" ");

        const day = parseInt(parts[0]);

        const month = parts[1]
            ? parts[1].replace(",", "")
            : "";

        const year = parseInt(parts[2]);


        const months = {
            Jan: 0,
            Feb: 1,
            Mar: 2,
            Apr: 3,
            May: 4,
            Jun: 5,
            Jul: 6,
            Aug: 7,
            Sep: 8,
            Oct: 9,
            Nov: 10,
            Dec: 11
        };


        return new Date(
            year,
            months[month],
            day
        );

    }

});

// tab-change
document.addEventListener("DOMContentLoaded", function () {

    const tabs = document.querySelectorAll(".partner-tab");

    const orderContent = document.querySelector(".order-tab-content");
    const poContent = document.querySelector(".po-tab-content");


    tabs.forEach(function (tab) {

        tab.addEventListener("click", function () {

            tabs.forEach(function (item) {
                item.classList.remove("active");
            });

            this.classList.add("active");


            if (this.dataset.filter === "Order Detail") {

                orderContent.style.display = "block";
                poContent.style.display = "none";

            }


            if (this.dataset.filter === "PO Details") {

                orderContent.style.display = "none";
                poContent.style.display = "block";

            }

        });

    });

});
// lead-popup
document.addEventListener("DOMContentLoaded", function () {

    /* =========================================
       SELECT ALL / ROW CHECKBOXES
    ========================================= */

    const selectAll = document.getElementById("selectAll");

    const rowCheckboxes = document.querySelectorAll(
        '.crm-lead-table tbody input[type="checkbox"]'
    );

    const bulkActionBar = document.getElementById("bulkActionBar");


    /* =========================================
       ASSIGN EMPLOYEE MODAL
    ========================================= */

    const assignEmployeeBtn =
        document.getElementById("assignEmployeeBtn");

    const assignEmployeeModal =
        document.getElementById("assignEmployeeModal");

    const closeAssignEmployeeModal =
        document.getElementById("closeAssignEmployeeModal");

    const confirmAssignEmployee =
        document.getElementById("confirmAssignEmployee");


    /* =========================================
       DOWNLOAD / DELETE
    ========================================= */

    const downloadListBtn =
        document.getElementById("downloadListBtn");

    const deleteLeadsBtn =
        document.getElementById("deleteLeadsBtn");


    /* =========================================
       GET SELECTED CHECKBOXES
    ========================================= */

    function getSelectedCheckboxes() {

        return document.querySelectorAll(
            '.crm-lead-table tbody input[type="checkbox"]:checked'
        );

    }


    /* =========================================
       UPDATE SELECT ALL
    ========================================= */

    function updateSelectAll() {

        const checkedCount =
            getSelectedCheckboxes().length;

        const totalCount =
            rowCheckboxes.length;


        if (!selectAll) {
            return;
        }


        if (checkedCount === 0) {

            selectAll.checked = false;
            selectAll.indeterminate = false;

        } else if (checkedCount === totalCount) {

            selectAll.checked = true;
            selectAll.indeterminate = false;

        } else {

            selectAll.checked = false;
            selectAll.indeterminate = true;

        }

    }


    /* =========================================
       UPDATE BOTTOM ACTION BAR
    ========================================= */

    function updateBulkActionBar() {

        if (!bulkActionBar) {
            return;
        }

        const selectedCount =
            getSelectedCheckboxes().length;

        if (selectedCount >= 1) {

            bulkActionBar.classList.add("show");

        } else {

            bulkActionBar.classList.remove("show");

        }

    }


    /* =========================================
       SELECT ALL
    ========================================= */

    if (selectAll) {

        selectAll.addEventListener("change", function () {

            const isChecked = this.checked;


            rowCheckboxes.forEach(function (checkbox) {

                checkbox.checked = isChecked;

            });


            this.indeterminate = false;

            updateBulkActionBar();

        });

    }


    /* =========================================
       INDIVIDUAL CHECKBOX
    ========================================= */

    rowCheckboxes.forEach(function (checkbox) {

        checkbox.addEventListener("change", function () {

            updateSelectAll();

            updateBulkActionBar();

        });

    });


    /* =========================================
       ASSIGN EMPLOYEE
    ========================================= */

    if (assignEmployeeBtn) {

        assignEmployeeBtn.addEventListener(
            "click",
            function () {

                const selectedCount =
                    getSelectedCheckboxes().length;


                if (selectedCount < 1) {
                    alert("Please select at least one lead.");
                    return;
                }

                if (!assignEmployeeModal) {
                    return;
                }

                assignEmployeeModal.classList.add("show");

            }
        );

    }


    /* =========================================
       CLOSE ASSIGN MODAL
    ========================================= */

    if (closeAssignEmployeeModal) {

        closeAssignEmployeeModal.addEventListener(
            "click",
            function () {

                assignEmployeeModal.classList.remove("show");

            }
        );

    }


    /* =========================================
       CLICK OUTSIDE MODAL
    ========================================= */

    if (assignEmployeeModal) {

        assignEmployeeModal.addEventListener(
            "click",
            function (event) {

                if (event.target === assignEmployeeModal) {

                    assignEmployeeModal.classList.remove(
                        "show"
                    );

                }

            }
        );

    }


    /* =========================================
       CONFIRM ASSIGN EMPLOYEE
    ========================================= */

    if (confirmAssignEmployee) {

        confirmAssignEmployee.addEventListener(
            "click",
            function () {

                const selectedEmployee =
                    document.querySelector(
                        'input[name="assignEmployee"]:checked'
                    );


                if (!selectedEmployee) {

                    alert(
                        "Please select an employee."
                    );

                    return;

                }

                const ids = [];
                getSelectedCheckboxes().forEach(function (checkbox) {
                    if (checkbox.value) {
                        ids.push(checkbox.value);
                    }
                });

                if (!ids.length) {
                    alert("Please select at least one lead.");
                    return;
                }

                const token = document.querySelector('meta[name="csrf-token"]');
                const assignUrl = assignEmployeeModal
                    ? assignEmployeeModal.getAttribute("data-assign-url")
                    : "/crm/leads/assign";

                fetch(assignUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": token ? token.content : "",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        assigned_to: selectedEmployee.value,
                        lead_ids: ids
                    })
                }).then(function (res) {
                    if (!res.ok) {
                        throw new Error("Assign failed");
                    }
                    window.location.reload();
                }).catch(function () {
                    alert("Could not assign employee.");
                });

            }
        );

    }


    /* =========================================
       DOWNLOAD LIST
    ========================================= */

    if (downloadListBtn) {

        downloadListBtn.addEventListener(
            "click",
            function () {

                const selectedCheckboxes =
                    getSelectedCheckboxes();


                if (selectedCheckboxes.length < 2) {
                    return;
                }


                const selectedLeads = [];


                selectedCheckboxes.forEach(
                    function (checkbox) {

                        const row =
                            checkbox.closest("tr");


                        if (!row) {
                            return;
                        }


                        const customerName =
                            row.querySelector(
                                ".customer h5"
                            );


                        if (customerName) {

                            selectedLeads.push(
                                customerName.textContent.trim()
                            );

                        }

                    }
                );


                console.log(
                    "Selected Leads:",
                    selectedLeads
                );


                /*
                 * Actual Excel/CSV download
                 * backend se baad mein connect kar sakte hain
                 */

            }
        );

    }


    /* =========================================
       DELETE LEADS
    ========================================= */

    if (deleteLeadsBtn) {

        deleteLeadsBtn.addEventListener(
            "click",
            function () {

                const selectedCheckboxes =
                    getSelectedCheckboxes();


                if (selectedCheckboxes.length < 2) {
                    return;
                }


                const selectedCount =
                    selectedCheckboxes.length;


                const confirmDelete =
                    confirm(
                        "Are you sure you want to delete " +
                        selectedCount +
                        " selected leads?"
                    );


                if (!confirmDelete) {
                    return;
                }


                selectedCheckboxes.forEach(
                    function (checkbox) {

                        const row =
                            checkbox.closest("tr");


                        if (row) {
                            row.remove();
                        }

                    }
                );


                /*
                 * RESET SELECT ALL
                 */

                selectAll.checked = false;
                selectAll.indeterminate = false;


                /*
                 * UPDATE ACTION BAR
                 */

                updateBulkActionBar();

            }
        );

    }


    /* =========================================
       LUCIDE ICONS
    ========================================= */

    if (typeof lucide !== "undefined") {

        lucide.createIcons();

    }

});