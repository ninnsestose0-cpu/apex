document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('checkout');
  if (!form) return;

  var subtotal = parseFloat(form.dataset.subtotal);
  var fee = parseFloat(form.dataset.fee);
  var cur = form.dataset.cur;
  var feeRow = document.getElementById('feeRow');
  var grand = document.getElementById('grand');
  var pay = document.getElementById('pay_method');
  var qrbox = document.getElementById('qrbox');

  function fmt(n) {
    return cur + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function refresh() {
    var service = form.querySelector('input[name="service_type"]:checked').value;

    // Show only the fields that belong to the chosen service
    form.querySelectorAll('[data-show]').forEach(function (box) {
      box.hidden = box.dataset.show !== service;
    });

    // Delivery fee and total
    var isDelivery = service === 'delivery';
    feeRow.hidden = !isDelivery;
    grand.textContent = fmt(subtotal + (isDelivery ? fee : 0));

    // QR reference number only for non-cash payments
    qrbox.hidden = pay.value === 'cash';
  }

  form.querySelectorAll('input[name="service_type"]').forEach(function (r) {
    r.addEventListener('change', refresh);
  });
  pay.addEventListener('change', refresh);
  refresh();
});

document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('checkout');
  if (!form) return;

  var subtotal = parseFloat(form.dataset.subtotal);
  var fee = parseFloat(form.dataset.fee);
  var cur = form.dataset.cur;
  var feeRow = document.getElementById('feeRow');
  var grand = document.getElementById('grand');
  var pay = document.getElementById('pay_method');
  var qrbox = document.getElementById('qrbox');

  function fmt(n) {
    return cur + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function refresh() {
    var service = form.querySelector('input[name="service_type"]:checked').value;

    // Show only the fields that belong to the chosen service
    form.querySelectorAll('[data-show]').forEach(function (box) {
      box.hidden = box.dataset.show !== service;
    });

    // Delivery fee and total
    var isDelivery = service === 'delivery';
    feeRow.hidden = !isDelivery;
    grand.textContent = fmt(subtotal + (isDelivery ? fee : 0));

    // QR reference number only for non-cash payments
    qrbox.hidden = pay.value === 'cash';
  }

  form.querySelectorAll('input[name="service_type"]').forEach(function (r) {
    r.addEventListener('change', refresh);
  });
  pay.addEventListener('change', refresh);
  refresh();
});