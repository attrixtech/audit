import * as bootstrap from 'bootstrap';
import Chart from 'chart.js/auto';

window.bootstrap = bootstrap;
window.Chart = Chart;

document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Auto-dismiss flash alerts after 5 seconds (CL-03)
    const scheduleAlertDismiss = (alertEl) => {
        window.setTimeout(() => {
            if (!document.body.contains(alertEl)) return;
            const instance = bootstrap.Alert.getOrCreateInstance(alertEl);
            instance.close();
        }, 5000);
    };

    document.querySelectorAll('.alert.alert-dismissible').forEach(scheduleAlertDismiss);

    // Helper to display an auto-dismissing flash banner without reloading
    const showDynamicAlert = (message, tone = 'success') => {
        const main = document.querySelector('main');
        if (!main) return;
        const alertEl = document.createElement('div');
        alertEl.className = `alert alert-${tone} alert-dismissible fade show border-0 shadow-sm`;
        alertEl.setAttribute('role', 'alert');
        alertEl.innerHTML = `<i class="bi bi-check-circle-fill me-2"></i><span></span><button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertEl.querySelector('span').textContent = message;
        main.prepend(alertEl);
        scheduleAlertDismiss(alertEl);
    };

    // In-place document approval without page reload (AC-06)
    document.querySelectorAll('.js-approve-doc-form').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                if (!res.ok) {
                    form.submit();
                    return;
                }
                const data = await res.json();
                const row = form.closest('tr');
                if (row) {
                    const statusCell = row.querySelector('.js-doc-status');
                    if (statusCell) {
                        statusCell.innerHTML = '<span class="badge badge-soft-success">Approved</span>';
                    }
                    const rejectBtn = row.querySelector('.js-reject-doc-btn');
                    if (rejectBtn) rejectBtn.remove();
                    form.remove();
                }

                // Update KPI counters if present
                const uploadedEl = document.querySelector('[data-kpi="uploaded"] .fw-bold');
                const approvedEl = document.querySelector('[data-kpi="approved"] .fw-bold');
                if (uploadedEl) {
                    const nextVal = Math.max(0, (parseInt(uploadedEl.textContent, 10) || 0) - 1);
                    uploadedEl.textContent = String(nextVal);
                }
                if (approvedEl) {
                    const nextVal = (parseInt(approvedEl.textContent, 10) || 0) + 1;
                    approvedEl.textContent = String(nextVal);
                }

                showDynamicAlert(data.message || 'Document approved.');
            } catch (err) {
                console.error('Approve failed', err);
                form.submit();
            }
        });
    });

    // Shared task status update helper
    const updateTaskStatus = async (taskId, newStatus) => {
        await fetch(`/tasks/${taskId}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ status: newStatus }),
        });
        window.location.reload();
    };

    // Mobile-friendly task status switcher on Kanban cards (AC-11)
    document.querySelectorAll('.js-task-status-select').forEach((select) => {
        select.addEventListener('change', async () => {
            const taskId = select.getAttribute('data-task-id');
            if (!taskId) return;
            select.disabled = true;
            try {
                await updateTaskStatus(taskId, select.value);
            } catch (err) {
                console.error('Status update failed', err);
                select.disabled = false;
            }
        });
    });

    // Kanban drag-and-drop status updates
    const cards = document.querySelectorAll('.kanban-card[data-task-id]');
    const columns = document.querySelectorAll('.kanban-dropzone[data-status]');

    let draggedTaskId = null;

    cards.forEach((card) => {
        card.addEventListener('dragstart', () => {
            draggedTaskId = card.getAttribute('data-task-id');
            card.classList.add('opacity-50');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('opacity-50');
        });
    });

    columns.forEach((col) => {
        col.addEventListener('dragover', (e) => {
            e.preventDefault();
            col.classList.add('border-primary');
        });
        col.addEventListener('dragleave', () => {
            col.classList.remove('border-primary');
        });
        col.addEventListener('drop', async (e) => {
            e.preventDefault();
            col.classList.remove('border-primary');
            const newStatus = col.getAttribute('data-status');
            if (!draggedTaskId || !newStatus) return;

            const draggedEl = document.querySelector(`.kanban-card[data-task-id="${draggedTaskId}"]`);
            if (draggedEl) {
                col.appendChild(draggedEl);
            }

            try {
                await updateTaskStatus(draggedTaskId, newStatus);
            } catch (err) {
                console.error('Status update failed', err);
            }
        });
    });
});
