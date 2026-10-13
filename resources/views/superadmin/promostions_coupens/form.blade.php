{{-- <!-- Coupon Modal -->
<div class="modal fade"
     id="couponModal"
     tabindex="-1"
     aria-labelledby="couponModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <!-- Modal Header -->
            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title" id="couponModalLabel">
                    Coupon Form
                </h5>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                </button>

            </div>

            <!-- Form Start -->
            <form id="couponForm" autocomplete="off">
                @csrf

                <div class="modal-body">

                    <!-- Hidden ID -->
                    <input type="hidden"
                           id="coupon_id"
                           name="coupon_id">

                    <!-- Coupon Code -->
                    <div class="mb-3">
                        <label for="code" class="form-label">
                            Coupon Code
                        </label>
                        <input type="text"
                               id="code"
                               name="code"
                               class="form-control"
                               placeholder="Enter coupon code"
                               required>
                    </div>

                    <!-- Discount -->
                    <div class="mb-3">
                        <label for="discount_percent" class="form-label">
                            Discount %
                        </label>
                        <input type="number"
                               id="discount_percent"
                               name="discount_percent"
                               class="form-control"
                               placeholder="Enter discount percentage"
                               required min="0" max="100">
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label for="status" class="form-label">
                            Status
                        </label>
                        <select id="status"
                                name="status"
                                class="form-select">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                </div>

                <!-- Footer -->
                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Close
                    </button>

                    <button type="submit"
                            class="btn btn-success">
                        Save Coupon
                    </button>

                </div>

            </form>
            <!-- Form End -->

        </div>

    </div>

</div>
<script>
    $(document).ready(function(){

        // Open modal for add coupon (reset and clear id)
        $('#addBtn').on('click', function(){
            $('#couponForm')[0].reset();
            $('#coupon_id').val('');
            $('#couponModal').modal('show');
        });

        // Optional: Prevent form double submission and enhance validation
        $('#couponForm').off('submit').on('submit', function(e){
            e.preventDefault();

            var code = $('#code').val().trim();
            var discount = $('#discount_percent').val();
            var status = $('#status').val();

            // Basic frontend validation
            if(!code) {
                alert('Coupon code is required.');
                $('#code').focus();
                return false;
            }
            if(discount === '' || isNaN(discount) || discount < 0 || discount > 100) {
                alert('Please enter a valid discount percentage between 0 and 100.');
                $('#discount_percent').focus();
                return false;
            }
            // You may add AJAX form submission here if required

            // By default, just close the modal after submit as placeholder (can be replaced by AJAX logic)
            $('#couponModal').modal('hide');
        });

    });
</script> --}}