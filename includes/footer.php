        </div> <!-- Close content-wrapper -->
    </div> <!-- Close main-content -->
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            if ($('.datatable').length) {
                $('.datatable').DataTable({
                    pageLength: 25,
                    responsive: true,
                    order: []
                });
            }
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
            
            // Confirm delete actions
            $('.confirm-delete').on('click', function() {
                return confirm('Are you sure you want to delete this? This action cannot be undone.');
            });
            
            // Prevent double form submission
            $('form').on('submit', function() {
                $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
            });
            
            // Format currency
            function formatCurrency(amount) {
                return '৳' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            }
            
            // Format date
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    
    <?php if (isset($custom_js)): ?>
    <script>
        <?php echo $custom_js; ?>
    </script>
    <?php endif; ?>
</body>
</html>