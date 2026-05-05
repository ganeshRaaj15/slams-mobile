<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0">

            <!-- MODAL HEADER -->
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-container d-flex align-items-center justify-content-center rounded-circle"
                         style="width: 48px; height: 48px; background: rgba(239, 68, 68, 0.15);">
                        <i class="bi bi-exclamation-triangle-fill fs-4 text-danger"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-danger">
                            Delete Laboratory
                        </h5>
                        <small class="text-muted">
                            This action cannot be undone
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- MODAL BODY -->
            <div class="modal-body pt-3">
                <p class="text-muted mb-2">
                    Are you sure you want to permanently delete this laboratory?
                </p>

                <!-- Optional: You could add JavaScript to show lab details here -->
                <div class="alert alert-danger border-0 mt-3">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    All related data and images will be permanently removed.
                </div>
            </div>

            <!-- MODAL FOOTER -->
            <div class="modal-footer border-0 pt-0">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>
                        Delete
                    </button>
                </form>

                <button type="button"
                        class="btn btn-outline-secondary px-4"
                        data-bs-dismiss="modal">
                    Cancel
                </button>
            </div>

        </div>
    </div>
</div>