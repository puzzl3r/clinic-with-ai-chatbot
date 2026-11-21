document.addEventListener('DOMContentLoaded', () => {
  const dateInput = document.getElementById('appointment_date');
  const timeGrid = document.getElementById('timeGrid');
  const form = document.getElementById('bookingForm');

  if (dateInput && timeGrid && form) {
    dateInput.addEventListener('change', async () => {
      const branchId = form.querySelector('[name="branch_id"]')?.value;
      if (!branchId || !dateInput.value) return;
      timeGrid.innerHTML = '<p class="empty-slots">Checking available times…</p>';
      try {
        const response = await fetch(`api/slots.php?branch=${encodeURIComponent(branchId)}&date=${encodeURIComponent(dateInput.value)}`);
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not load slots');
        if (!data.slots.length) {
          timeGrid.innerHTML = '<p class="empty-slots">No available times for this date. Please select another day.</p>';
          return;
        }
        timeGrid.innerHTML = data.slots.map(slot => {
          const [h,m] = slot.split(':').map(Number);
          const label = new Date(2000,0,1,h,m).toLocaleTimeString([], {hour:'numeric',minute:'2-digit'});
          return `<label class="time-option"><input type="radio" name="appointment_time" value="${slot}" required><span>${label}</span></label>`;
        }).join('');
      } catch (error) {
        timeGrid.innerHTML = '<p class="empty-slots">Unable to refresh times. Please reload the page.</p>';
      }
    });
  }

  document.querySelectorAll('[data-copy]').forEach(button => {
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(button.dataset.copy);
        const original = button.textContent;
        button.textContent = 'Copied!';
        setTimeout(() => button.textContent = original, 1400);
      } catch (error) {
        button.textContent = button.dataset.copy;
      }
    });
  });
});
