<?= $this->extend('layouts/main_user') ?>
<?= $this->section('content') ?>
<!-- ============================================================
     CONTACT PAGE CONTENT
     ============================================================ -->
<div class="contact-page">
    <div class="container">
        
        <!-- Page Header -->
        <div class="contact-header">
            <h1 class="contact-title">Contact Us</h1>
            <p class="contact-subtitle">
                Get in touch with the Faculty of Mechanical & Manufacturing Engineering (FKMP) at UTHM.
                Our team is here to assist you with laboratory inquiries and booking support.
            </p>
        </div>

        <div class="row gy-4">
            
            <!-- =========================================== -->
            <!-- LEFT COLUMN: FKMP Information              -->
            <!-- =========================================== -->
            <div class="col-lg-6">
                <div class="contact-card">
                    <!-- Faculty Building Image -->
                    <img src="<?= base_url('images/fkmp/FKMP.jpeg') ?>"
                         alt="FKMP Building, UTHM"
                         class="contact-img"
                         onerror="this.src='https://images.unsplash.com/photo-1562774053-701939374585?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80'">

                    <!-- Faculty Information -->
                    <h4 class="faculty-title">
                        <i class="bi bi-building"></i>
                        Faculty of Mechanical & Manufacturing Engineering
                    </h4>
                    
                    <p class="faculty-info">
                        <strong>Universiti Tun Hussein Onn Malaysia (UTHM)</strong><br>
                        86400, Parit Raja, Batu Pahat,<br>
                        Johor, Malaysia
                    </p>

                    <!-- Contact Details -->
                    <div class="contact-details">
                        <div class="contact-detail">
                            <i class="bi bi-telephone-fill"></i>
                            <div>
                                <strong>Phone:</strong> +607 4537703
                            </div>
                        </div>
                        
                        <div class="contact-detail">
                            <i class="bi bi-printer-fill"></i>
                            <div>
                                <strong>Fax:</strong> +607 4536080
                            </div>
                        </div>
                        
                        <div class="contact-detail">
                            <i class="bi bi-clock-fill"></i>
                            <div>
                                <strong>Operating Hours:</strong> 8:00 AM - 5:00 PM (Monday - Friday)
                            </div>
                        </div>
                        
                        <div class="contact-detail">
                            <i class="bi bi-geo-alt-fill"></i>
                            <div>
                                <strong>Location:</strong> Main Campus, Parit Raja
                            </div>
                        </div>
                    </div>

                    <!-- Additional Note -->
                    <div class="contact-note">
                        <p>
                            <i class="bi bi-info-circle-fill"></i>
                            For general inquiries about our facilities and academic programs, 
                            feel free to contact us during office hours.
                        </p>
                    </div>
                </div>
            </div>

            <!-- =========================================== -->
            <!-- RIGHT COLUMN: Staff Contacts                -->
            <!-- =========================================== -->
            <div class="col-lg-6">
                <div class="contact-card">
                    <h4 class="staff-section-title">
                        <i class="bi bi-people-fill"></i>
                        Key Personnel Contacts
                    </h4>

                    <!-- Office Secretary -->
                    <div class="staff-card">
                        <img src="<?= base_url('images/staff/haslina_placeholder.jpg') ?>"
                             class="staff-photo"
                             alt="Mrs. Haslina binti Abd. Rashid"
                             onerror="this.src='https://images.unsplash.com/photo-1580489944761-15a19d654956?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80'">

                        <div class="staff-info">
                            <div class="staff-name">Mrs. Haslina binti Abd. Rashid</div>
                            <div class="staff-role">Office Secretary, Dean Office</div>
                            
                            <div class="staff-contact">
                                <a href="tel:+6074537703" class="contact-link">
                                    <i class="bi bi-telephone-fill"></i>
                                    <span>+607 4537703</span>
                                </a>
                                
                                <a href="mailto:haslinar@uthm.edu.my" class="contact-link">
                                    <i class="bi bi-envelope-fill"></i>
                                    <span>haslinar@uthm.edu.my</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Assistant Registrar -->
                    <div class="staff-card">
                        <img src="<?= base_url('images/staff/asyarofah_placeholder.jpg') ?>"
                             class="staff-photo"
                             alt="Mrs. Asyarofah bt. Othman"
                             onerror="this.src='https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80'">

                        <div class="staff-info">
                            <div class="staff-name">Mrs. Asyarofah bt. Othman</div>
                            <div class="staff-role">Assistant Registrar, Academic Division</div>
                            
                            <div class="staff-contact">
                                <a href="tel:+6074537351" class="contact-link">
                                    <i class="bi bi-telephone-fill"></i>
                                    <span>+607 4537351</span>
                                </a>
                                
                                <a href="mailto:asyarofa@uthm.edu.my" class="contact-link">
                                    <i class="bi bi-envelope-fill"></i>
                                    <span>asyarofa@uthm.edu.my</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Web Administrator -->
                    <div class="staff-card">
                        <img src="<?= base_url('images/staff/azwan_placeholder.jpg') ?>"
                             class="staff-photo"
                             alt="Dr. Azwan Bin Sapit"
                             onerror="this.src='https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80'">

                        <div class="staff-info">
                            <div class="staff-name">Dr. Azwan Bin Sapit</div>
                            <div class="staff-role">Web Administrator</div>
                            
                            <div class="staff-contact">
                                <a href="tel:+6074538470" class="contact-link">
                                    <i class="bi bi-telephone-fill"></i>
                                    <span>+607 4538470</span>
                                </a>
                                
                                <a href="mailto:azwans@uthm.edu.my" class="contact-link">
                                    <i class="bi bi-envelope-fill"></i>
                                    <span>azwans@uthm.edu.my</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Laboratory Support Note -->
                    <div class="contact-note">
                        <p>
                            <i class="bi bi-shield-check"></i>
                            For laboratory-related inquiries or booking assistance through SLAMS, 
                            please contact the relevant personnel listed above.
                        </p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Map Section with Google Maps Embed -->
        <div class="map-section">
            <h4 class="map-title">
                <i class="bi bi-geo-alt-fill"></i>
                Campus Location
            </h4>
            
            <!-- Google Maps Embed -->
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3982.919158657578!2d103.0859238!3d1.8564176!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d05fa8c1d6b90f%3A0xfa4a13957533a50f!2sFaculty%20of%20Mechanical%20%26%20Manufacturing%20Engineering%2C%20Universiti%20Tun%20Hussein%20Onn%20Malaysia%20(UTHM)!5e0!3m2!1sen!2smy!4v1700000000000!5m2!1sen!2smy" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
            
            <!-- Map Action Buttons -->
            <div class="map-actions">
                <a href="https://www.google.com/maps/dir//Faculty+of+Mechanical+%26+Manufacturing+Engineering,+Universiti+Tun+Hussein+Onn+Malaysia+(UTHM),+86400+Parit+Raja,+Batu+Pahat,+Johor/@1.8564176,103.0859238,17z/data=!4m9!4m8!1m0!1m5!1m1!1s0x31d05fa8c1d6b90f:0xfa4a13957533a50f!2m2!1d103.0881125!2d1.8564176!3e0?entry=ttu" 
                   target="_blank" 
                   class="map-btn">
                    <i class="bi bi-signpost"></i>
                    Get Directions
                </a>
                
                <a href="https://www.google.com/maps/place/Faculty+of+Mechanical+%26+Manufacturing+Engineering,+Universiti+Tun+Hussein+Onn+Malaysia+(UTHM)/data=!4m7!3m6!1s0x31d05fa8c1d6b90f:0xfa4a13957533a50f!8m2!3d1.8564176!4d103.0881125!16s%2Fg%2F11hz_83sj7!19sChIJD7nWwahf0DERD6UzdZUTSvo?authuser=0&hl=en&rclk=1" 
                   target="_blank" 
                   class="map-btn">
                    <i class="bi bi-google"></i>
                    Open in Google Maps
                </a>
                
                <a href="https://waze.com/ul?ll=1.8564176,103.0881125&navigate=yes" 
                   target="_blank" 
                   class="map-btn">
                    <i class="bi bi-signpost-split"></i>
                    Open in Waze
                </a>
            </div>
            
            <!-- Map Information -->
            <div class="map-info">
                <p>
                    <strong>Exact Coordinates:</strong> 1.8564176 N, 103.0881125 E<br>
                    <strong>Parking:</strong> Available at nearby faculty parking lots<br>
                    <strong>Public Transport:</strong> UTHM shuttle bus stops nearby
                </p>
            </div>
        </div>

    </div>
</div>

<script>
// Add interactive effects
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effect to staff cards
    const staffCards = document.querySelectorAll('.staff-card');
    
    staffCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.cursor = 'pointer';
        });
        
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking a link
            if (e.target.tagName === 'A' || e.target.closest('a')) {
                return;
            }
            
        });
    });
    
});
</script>

<?= $this->endSection() ?>
