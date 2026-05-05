<div class="modal fade" id="bookingDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header" style="background:var(--fkmp-maroon); color:white;">
                <h5 class="modal-title">Booking Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <h5 class="fw-bold" id="detailLab"></h5>

                <hr>

                <p><strong>Date:</strong> <span id="detailDate"></span></p>
                <p><strong>Time:</strong> <span id="detailTime"></span></p>

                <hr>

                <h6 class="fw-semibold">Applicants</h6>
                <ul id="detailApplicants"></ul>

                <h6 class="fw-semibold mt-3">Assets Used</h6>
                <ul id="detailAssets"></ul>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>
