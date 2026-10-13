{{-- Include at the end of the order wizard form (after the main order scripts): @include('superadmin.orders.partials.plan-remove-persist') --}}
@push('scripts')
    <script>
        (function($) {
            var PLAN_DRAFT_KEY_PREFIX = 'sa_order_items_';
            var GST_RATE = 18;

            function orderIdFromForm() {
                return parseInt($('#order_id').val() || '0', 10) || 0;
            }

            function collectPlanRowsFromDom() {
                var rows = [];
                var pid = parseInt($('#provider_id').val() || '0', 10) || 0;
                $('#order_items_wrap .item-row').each(function() {
                    var $r = $(this);
                    rows.push({
                        plan_id: $r.find('.item-plan').val() || $r.attr('data-saved-plan-id') || '',
                        quantity: $r.find('.item-qty').val() || $r.attr('data-saved-qty') || 1,
                        unit_price: $r.find('.item-price').val() || $r.attr('data-saved-unit-price') || '',
                        variant_id: $r.find('.item-variant-id').val() || '',
                        item_name: $r.find('.item-name').val() || '',
                        description: $r.find('.item-desc').val() || '',
                        provider_id: pid
                    });
                });
                return rows;
            }

            function storePlanDraft(rows) {
                var oid = orderIdFromForm();
                if (oid <= 0) {
                    return;
                }
                try {
                    sessionStorage.setItem(PLAN_DRAFT_KEY_PREFIX + oid, JSON.stringify(rows));
                } catch (e) {}
            }

            function readPlanDraft() {
                var oid = orderIdFromForm();
                if (oid <= 0) {
                    return null;
                }
                try {
                    var raw = sessionStorage.getItem(PLAN_DRAFT_KEY_PREFIX + oid);
                    if (!raw) {
                        return null;
                    }
                    var parsed = JSON.parse(raw);
                    return Array.isArray(parsed) ? parsed : null;
                } catch (e) {
                    return null;
                }
            }

            function clearPlanDraft() {
                var oid = orderIdFromForm();
                if (oid <= 0) {
                    return;
                }
                try {
                    sessionStorage.removeItem(PLAN_DRAFT_KEY_PREFIX + oid);
                } catch (e) {}
            }

            function recalcOrderSummaryFromDom() {
                var subtotal = 0;
                $('#order_items_wrap .item-row').each(function() {
                    subtotal += (parseFloat($(this).find('.item-price').val()) || 0) *
                        (parseInt($(this).find('.item-qty').val(), 10) || 1);
                });
                $('#sum_subtotal').text(subtotal.toFixed(2));
                var applyGst = $('#apply_gst_toggle').is(':checked');
                $('#apply_gst_hidden').val(applyGst ? 1 : 0);
                var manualDisc = parseFloat($('#discount_amount').val()) || 0;
                var taxable = Math.max(subtotal - manualDisc, 0);
                if (manualDisc > 0) {
                    $('#row_discount').show();
                    $('#sum_discount').text(manualDisc.toFixed(2));
                } else {
                    $('#row_discount').hide();
                }
                $('#sum_taxable').text(taxable.toFixed(2));
                var gst = applyGst ? +(taxable * GST_RATE / 100).toFixed(2) : 0;
                $('#sum_gst').text(gst.toFixed(2));
                $('#sum_total').text((taxable + gst).toFixed(2));
                $('#subtotal_hidden').val($('#sum_subtotal').text());
                $('#gst_amount_hidden').val($('#sum_gst').text());
                $('#tax_amount_hidden').val($('#sum_gst').text());
                $('#total_amount_hidden').val($('#sum_total').text());
            }

            function reindexPlanRows() {
                var itemIdx = 0;
                $('#order_items_wrap .item-row').each(function() {
                    var $row = $(this);
                    $row.attr('data-idx', itemIdx);
                    $row.find('[name^="items["]').each(function() {
                        var $el = $(this);
                        var name = $el.attr('name') || '';
                        var match = name.match(/^items\[\d+\]\[(\w+)\]$/);
                        if (match) {
                            $el.attr('name', 'items[' + itemIdx + '][' + match[1] + ']');
                        }
                    });
                    itemIdx++;
                });
            }

            function buildSilentSaveFormData() {
                var fd = new FormData();
                var $form = $('#sa_order_form')[0];
                if (!$form) {
                    return fd;
                }

                Array.prototype.forEach.call($form.elements, function(el) {
                    if (!el.name || el.disabled) {
                        return;
                    }
                    if (el.name.indexOf('items[') === 0 || el.name.indexOf('plans[') === 0) {
                        return;
                    }
                    if (el.type === 'file') {
                        return;
                    }
                    if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
                        return;
                    }
                    fd.append(el.name, el.value);
                });

                var rows = collectPlanRowsFromDom();
                var itemIdx = 0;
                rows.forEach(function(row) {
                    var planId = parseInt(row.plan_id, 10) || 0;
                    if (planId <= 0) {
                        return;
                    }
                    var qty = parseInt(row.quantity, 10) || 1;
                    var unitPrice = row.unit_price || '0';
                    fd.append('items[' + itemIdx + '][plan_id]', String(planId));
                    fd.append('items[' + itemIdx + '][quantity]', String(qty));
                    fd.append('items[' + itemIdx + '][unit_price]', String(unitPrice));
                    if (row.variant_id) {
                        fd.append('items[' + itemIdx + '][variant_id]', String(row.variant_id));
                    }
                    if (row.item_name) {
                        fd.append('items[' + itemIdx + '][item_name]', String(row.item_name));
                    }
                    if (row.description) {
                        fd.append('items[' + itemIdx + '][description]', String(row.description));
                    }
                    fd.append('plans[' + itemIdx + '][plan_id]', String(planId));
                    fd.append('plans[' + itemIdx + '][quantity]', String(qty));
                    fd.append('plans[' + itemIdx + '][unit_price]', String(unitPrice));
                    itemIdx++;
                });

                var oid = orderIdFromForm();
                if (oid > 0) {
                    fd.set('order_id', String(oid));
                }
                fd.set('defer_po_save', '1');
                fd.set('send_notifications', '0');
                return fd;
            }

            function silentSaveOrderItems() {
                return $.ajax({
                    url: @json(route('superadmin.orders.save')),
                    type: 'POST',
                    data: buildSilentSaveFormData(),
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
            }

            function removePlanRow($row) {
                if ($('#order_items_wrap .item-row').length <= 1) {
                    if (typeof alertify !== 'undefined') {
                        alertify.warning('At least one plan is required.');
                    }
                    return false;
                }
                $row.remove();
                reindexPlanRows();
                recalcOrderSummaryFromDom();
                return true;
            }

            $(function() {
                if (!$('#sa_order_form').length || !$('#order_items_wrap').length) {
                    return;
                }

                // Replace default handler so remove persists without clicking Update order.
                $('#order_items_wrap').off('click', '.order-item-remove, .btn-remove-item');
                $('#order_items_wrap').on('click', '.order-item-remove, .btn-remove-item', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var $row = $(e.currentTarget).closest('.item-row');
                    if (!$row.length) {
                        $row = $(e.currentTarget).closest('.order-item-card');
                    }
                    if (!removePlanRow($row)) {
                        return;
                    }

                    var rows = collectPlanRowsFromDom();
                    storePlanDraft(rows);

                    var oid = orderIdFromForm();
                    if (oid <= 0) {
                        if (typeof alertify !== 'undefined') {
                            alertify.message('Plan removed.');
                        }
                        return;
                    }

                    silentSaveOrderItems().done(function(data) {
                        if (data && data.error) {
                            if (typeof alertify !== 'undefined') {
                                alertify.error(data.message || 'Plan removed, but save failed. Click Update order.');
                            }
                            return;
                        }
                        clearPlanDraft();
                        if (typeof alertify !== 'undefined') {
                            alertify.success((data && data.message) || 'Plan removed.');
                        }
                    }).fail(function(xhr) {
                        var msg = 'Plan removed from list, but save failed. Click Update order.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        if (typeof alertify !== 'undefined') {
                            alertify.error(msg);
                        }
                    });
                });

                $('#sa_order_form').on('submit.planPersist', function() {
                    clearPlanDraft();
                });
            });
        })(jQuery);
    </script>
@endpush
