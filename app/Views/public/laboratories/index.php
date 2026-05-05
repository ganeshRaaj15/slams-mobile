<?= $this->extend('layouts/main_user') ?>
<?= $this->section('content') ?>
<!-- ============================================================
     LABORATORY PAGE CONTENT
     ============================================================ -->
<div class="lab-page">
    
    <!-- HERO SECTION - Now positioned closer to navbar -->
    <section class="lab-hero">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="lab-title">FKMP Laboratory Directory</h1>
                <p class="lab-subtitle">
                    Explore our state-of-the-art laboratories and begin your equipment booking journey.
                    Discover facilities designed for innovation and research excellence.
                </p>

                <!-- Search Box -->
                <div class="lab-search-box">
                    <form method="get" class="d-flex flex-column flex-md-row gap-2" id="searchForm">
                        <input type="text"
                               name="q"
                               id="searchInput"
                               value="<?= esc($search ?? '') ?>"
                               class="form-control search-input"
                               placeholder="Search laboratories by name, room, or PIC..."
                               autocomplete="off">

                        <button class="btn search-btn" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                    <!-- Search Indicator -->
                    <div class="search-indicator" id="searchIndicator">
                        <?php if (! empty($search)): ?>
                            <span>Showing results for: <strong><?= esc($search) ?></strong></span>
                            <button class="clear-search-btn ms-2" id="clearFilterBtn" type="button">Clear</button>
                        <?php else: ?>
                            <span>Search by laboratory name, room, or PIC</span>
                            <button class="clear-search-btn ms-2" id="clearFilterBtn" type="button" style="display: none;">Clear</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <div class="container pb-5">
        
        <!-- Laboratory Count -->
        <div class="text-center">
            <span class="lab-count" id="labCount">
                <i class="bi bi-building me-2"></i>
                <?= count($labs) ?> <?= count($labs) === 1 ? 'Laboratory' : 'Laboratories' ?> Available
            </span>
        </div>

        <!-- Loading Spinner -->
        <div class="loading-spinner" id="loadingSpinner"></div>

        <!-- If no labs found (Initially hidden) -->
        <div class="no-results" id="noResults" style="display: <?= empty($labs) ? 'block' : 'none' ?>;">
            <div class="no-results-icon">
                <i class="bi bi-buildings"></i>
            </div>
            <h4 class="fw-semibold">No laboratories found</h4>
            <p class="text-muted" id="noResultsText">Try adjusting your search keywords or browse all available laboratories.</p>
            
            <button id="clearFilterBtnAlt" class="clear-search-btn" style="display: <?= !empty($search) ? 'inline-flex' : 'none' ?>;">
                <i class="bi bi-arrow-left me-1"></i>
                View All Laboratories
            </button>
        </div>

        <!-- Laboratories Grid -->
        <div class="row g-4" id="labsGrid">
            <?php foreach ($labs as $lab): ?>
                <div class="col-md-6 col-lg-4 lab-item" 
                     data-name="<?= esc(strtolower($lab['name'])) ?>"
                     data-room="<?= esc(strtolower($lab['room'] ?? '')) ?>"
                     data-pic="<?= esc(strtolower($lab['pic_name'] ?? '')) ?>"
                     data-capacity="<?= esc($lab['capacity'] ?? '') ?>">
                    <div class="lab-card">
                        
                        <!-- Laboratory Image -->
                        <?php if (!empty($lab['image'])): ?>
                            <img src="<?= base_url($lab['image']) ?>"
                                 alt="<?= esc($lab['name']) ?>"
                                 class="lab-card-img">
                        <?php else: ?>
                            <div class="lab-placeholder">
                                <i class="bi bi-building"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Laboratory Content -->
                        <div class="lab-card-content">
                            <h5 class="lab-name"><?= esc($lab['name']) ?></h5>
                            
                            <div class="lab-details">
                                <?php if (!empty($lab['room'])): ?>
                                    <div class="lab-detail">
                                        <i class="bi bi-door-open"></i>
                                        <span>Room <?= esc($lab['room']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($lab['capacity'])): ?>
                                    <div class="lab-detail">
                                        <i class="bi bi-people"></i>
                                        <span>Capacity: <?= esc($lab['capacity']) ?> people</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($lab['equipment_count'])): ?>
                                    <div class="lab-detail">
                                        <i class="bi bi-tools"></i>
                                        <span><?= esc($lab['equipment_count']) ?> equipment available</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Person in Charge -->
                            <?php if (!empty($lab['pic_name'])): ?>
                                <div class="lab-pic">
                                    <span class="lab-pic-label">Person in Charge:</span>
                                    <span class="lab-pic-name"><?= esc($lab['pic_name']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Footer with Action Button -->
                        <div class="lab-card-footer">
                            <a href="<?= site_url('/laboratories/' . $lab['id']) ?>"
                               class="btn view-btn">
                                <i class="bi bi-eye me-1"></i> View Details & Book
                            </a>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

</div>

<script>
// Real-time search filtering
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const labsGrid = document.getElementById('labsGrid');
    const labItems = document.querySelectorAll('.lab-item');
    const labCount = document.getElementById('labCount');
    const noResults = document.getElementById('noResults');
    const noResultsText = document.getElementById('noResultsText');
    const clearFilterBtn = document.getElementById('clearFilterBtn');
    const clearFilterBtnAlt = document.getElementById('clearFilterBtnAlt');
    const searchIndicator = document.getElementById('searchIndicator');
    const searchIndicatorText = searchIndicator ? searchIndicator.querySelector('span') : null;
    const loadingSpinner = document.getElementById('loadingSpinner');
    
    let filterTimeout;
    let isFiltering = false;

    // Function to filter labs
    function filterLabs(searchTerm) {
        isFiltering = true;
        loadingSpinner.style.display = 'block';
        
        // Hide all labs initially
        labItems.forEach(item => {
            item.classList.add('lab-hidden');
        });
        
        // Show matching labs
        let visibleCount = 0;
        labItems.forEach(item => {
            const name = item.dataset.name || '';
            const room = item.dataset.room || '';
            const pic = item.dataset.pic || '';
            const capacity = item.dataset.capacity || '';
            
            const searchLower = searchTerm.toLowerCase();
            const matches = name.includes(searchLower) || 
                          room.includes(searchLower) || 
                          pic.includes(searchLower) ||
                          capacity.includes(searchTerm);
            
            if (matches || searchTerm === '') {
                item.classList.remove('lab-hidden');
                visibleCount++;
            }
        });
        
        // Update count and show/hide no results
        setTimeout(() => {
            updateResults(visibleCount, searchTerm);
            isFiltering = false;
            loadingSpinner.style.display = 'none';
            
            // Animate visible cards
            animateVisibleCards();
        }, 300);
    }
    
        // Update results display
    function updateResults(count, searchTerm) {
        // Update count badge
        labCount.innerHTML = `<i class="bi bi-building me-2"></i>${count} ${count === 1 ? 'Laboratory' : 'Laboratories'} Available`;

        // Update search indicator
        if (searchIndicatorText) {
            if (searchTerm.trim()) {
                searchIndicatorText.innerHTML = `Showing results for: <strong>${searchTerm}</strong>`;
            } else {
                searchIndicatorText.textContent = 'Search by laboratory name, room, or PIC';
            }
        }
        if (searchIndicator) {
            searchIndicator.style.display = 'block';
        }

        // Show/hide no results message
        if (count === 0 && searchTerm.trim()) {
            noResultsText.textContent = `No laboratories found for "${searchTerm}". Try different keywords.`;
            noResults.style.display = 'block';
            if (clearFilterBtn) {
                clearFilterBtn.style.display = 'inline-flex';
            }
            if (clearFilterBtnAlt) {
                clearFilterBtnAlt.style.display = 'inline-flex';
            }
        } else if (count === 0) {
            noResultsText.textContent = 'No laboratories available.';
            noResults.style.display = 'block';
            if (clearFilterBtn) {
                clearFilterBtn.style.display = 'none';
            }
            if (clearFilterBtnAlt) {
                clearFilterBtnAlt.style.display = 'none';
            }
        } else {
            noResults.style.display = 'none';
            if (clearFilterBtn) {
                clearFilterBtn.style.display = searchTerm.trim() ? 'inline-flex' : 'none';
            }
            if (clearFilterBtnAlt) {
                clearFilterBtnAlt.style.display = searchTerm.trim() ? 'inline-flex' : 'none';
            }
        }
    }

    // Animate visible cards
    function animateVisibleCards() {
        document.querySelectorAll('.lab-item').forEach(card => {
            card.style.opacity = '';
            card.style.transition = '';
        });
    }
    
    // Initial animation
    animateVisibleCards();
    
    // Search input event listener with debouncing
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        
        // Clear previous timeout
        clearTimeout(filterTimeout);
        
        // Show loading spinner for searches longer than 1 character
        if (searchTerm.length > 1 && !isFiltering) {
            loadingSpinner.style.display = 'block';
        }
        
        // Set timeout for debouncing (300ms delay)
        filterTimeout = setTimeout(() => {
            filterLabs(searchTerm);
        }, 300);
    });
    
            // Clear filter button
    [clearFilterBtn, clearFilterBtnAlt].forEach(function(button) {
        if (!button) {
            return;
        }
        button.addEventListener('click', function() {
            searchInput.value = '';
            filterLabs('');
            searchInput.focus();
        });
    });

    // Form submission handling
    document.getElementById('searchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const searchTerm = searchInput.value.trim();
        
        if (searchTerm) {
            // If there's a search term, filter immediately
            filterLabs(searchTerm);
            
            // Smooth scroll to results
            setTimeout(() => {
                window.scrollTo({
                    top: document.querySelector('.container.pb-5').offsetTop - 100,
                    behavior: 'smooth'
                });
            }, 100);
        }
    });
    
    // Add keyboard shortcuts
    searchInput.addEventListener('keydown', function(e) {
        // Clear search with Escape key
        if (e.key === 'Escape') {
            this.value = '';
            filterLabs('');
        }
        
        // Focus on first result with Enter (when results are filtered)
        if (e.key === 'Enter' && this.value.trim()) {
            const firstVisibleLab = document.querySelector('.lab-item:not(.lab-hidden)');
            if (firstVisibleLab) {
                e.preventDefault();
                const viewBtn = firstVisibleLab.querySelector('.view-btn');
                if (viewBtn) {
                    window.location.href = viewBtn.href;
                }
            }
        }
    });
    
    // Add click effect to lab cards
    document.querySelectorAll('.lab-item').forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking the view button
            if (e.target.closest('.view-btn')) {
                return;
            }
            
            // Navigate to lab detail page
            const link = this.querySelector('.view-btn');
            if (link) {
                window.location.href = link.href;
            }
        });
    });
    
    // Initialize with any existing search term
    const initialSearch = searchInput.value.trim();
    if (initialSearch) {
        filterLabs(initialSearch);
    }
});
</script>

<?= $this->endSection() ?>
