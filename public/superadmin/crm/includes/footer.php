<!-- Add Credit -->
<div class="credit-modal" id="creditModal">
    <div class="credit-modal-box">

        <button class="credit-close" type="button" data-modal-close>
            &times;
        </button>

        <h2>Add Account Credit</h2>

        <p class="credit-info">
            Current allocation: ₹150,691.76 · Available: ₹50,000.00
        </p>

        <form>
            <div class="form-group">
                <label>
                    Amount to add (₹) <span>*</span>
                </label>

                <input type="number" placeholder="Eg. 50,000">
            </div>

            <div class="form-group">
                <label>
                    Description <span>*</span>
                </label>

                <textarea
                    rows="5"
                    placeholder="Reasoning for increase Credit"></textarea>
            </div>

            <div class="text-center">
                <button type="submit" class="submit-btn">
                    Submit
                </button>
            </div>
        </form>

    </div>
</div>


<!-- Approve Credit Allocation -->
<!-- Approve Credit Allocation -->
<div class="credit-modal" id="approveCreditModal">
    <div class="credit-modal-box approve-credit-box">

        <button class="credit-close" type="button" data-modal-close>
            &times;
        </button>

        <h2>Approve credit allocation</h2>

        <p class="credit-request-info">
            Request #10 · Mittal Solanki
        </p>

        <p class="credit-request-info">
            Current allocation: ₹150,691.76 · Available: ₹50,000.00
        </p>

        <form>

            <div class="form-group">
                <label>
                    Amount to add (₹) <span>*</span>
                </label>

                <input
                    type="number"
                    placeholder="Eg. 50,000">

                <small class="form-help">
                    You can approve a different amount than requested.
                    This value is added to credit allocation.
                </small>
            </div>


            <div class="form-group">
                <label>
                    Description <span>*</span>
                </label>

                <textarea
                    rows="5"
                    placeholder="Reasoning for increase Credit"></textarea>
            </div>
            <div class="text-center">
                <button type="submit" class="submit-btn">Approve</button>
            </div>

        </form>

    </div>
</div>
<!-- Reject Credit Request Modal -->
<div class="credit-modal" id="rejectCreditModal">
    <div class="credit-modal-box reject-credit-box">

        <button class="credit-close" type="button" data-modal-close>
            &times;
        </button>

        <h2>Reject this credit request?</h2>

        <p class="credit-request-info">
            Request #10 · Mittal Solanki
        </p>

        <p class="credit-request-info">
            Reject allocation: ₹150,691.76 · Available: ₹50,000.00
        </p>

        <form class="form-group reject-reason">
            <div class="form-group">
                <label>
                    rejection reason <span>*</span>
                </label>

                <textarea rows="5" placeholder="Reasoning for Reject this credit request"></textarea>
            </div>
            <div class="text-center">
                <button type="submit" class="submit-btn">Reject</button>
            </div>
        </form>



    </div>
</div>
<!-- Scripts -->
<!-- =========================
     BULK ACTION BAR
========================== -->

<div class="bulk-action-bar" id="bulkActionBar">

    <button type="button" id="assignEmployeeBtn">
        Assign Employee
    </button>

    <button type="button" id="downloadListBtn">
        Download List
    </button>

    <button type="button" id="deleteLeadsBtn">
        Delete Leads
    </button>

</div>

<!-- employee -->
<!-- =========================
     ASSIGN EMPLOYEE POPUP
========================= -->

<div id="assignEmployeeModal">

    <div id="assignEmployeeModalBox">

        <button type="button" id="closeAssignEmployeeModal">
            <i data-lucide="x"></i>
        </button>

        <h3>
            ASSIGN EMPLOYEE TO SELECTED LEADS
        </h3>


        <div id="assignEmployeeList">

            <label>
                <input type="radio" name="assignEmployee" value="Namrata Singh">

                <span>
                    <span class="assign-avatar">N</span>
                    Namrata Singh
                </span>

                <strong>50</strong>
            </label>


            <label>
                <input type="radio" name="assignEmployee" value="Pranali Jadhav">

                <span>
                    <span class="assign-avatar">P</span>
                    Pranali Jadhav
                </span>

                <strong>100</strong>
            </label>


            <label>
                <input type="radio" name="assignEmployee" value="Divya Sharma">

                <span>
                    <span class="assign-avatar">D</span>
                    Divya Sharma
                </span>

                <strong>24</strong>
            </label>


            <label>
                <input type="radio" name="assignEmployee" value="Vijay Maurya">

                <span>
                    <span class="assign-avatar">V</span>
                    Vijay Maurya
                </span>

                <strong>85</strong>
            </label>


            <label>
                <input type="radio" name="assignEmployee" value="Kavya Nikam">

                <span>
                    <span class="assign-avatar">K</span>
                    Kavya Nikam
                </span>

                <strong>75</strong>
            </label>


            <label>
                <input type="radio" name="assignEmployee" value="Vidhi Nayak">

                <span>
                    <span class="assign-avatar">V</span>
                    Vidhi Nayak
                </span>

                <strong>63</strong>
            </label>

        </div>

        <div class="submit-wrap">
            <button type="button" class="submit-btn">
                Confirm
            </button>
        </div>
    </div>

</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="script.js"></script>
<script src="crm.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.lucide) {
            lucide.createIcons();
        }
    });
</script>

</body>

</html>
