/**
 * Starcast CSV Importer Admin JavaScript
 */

(function($) {
    'use strict';
    
    var StarcastCSVImporter = {
        
        // Properties
        file: null,
        fileInfo: null,
        postType: null,
        mapping: {},
        options: {},
        importId: null,
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.initDragDrop();
            
            // Load history if on history page
            if ($('#history-table-body').length) {
                this.loadHistory();
            }
        },
        
        // Bind events
        bindEvents: function() {
            var self = this;
            
            // File upload
            $('#browse-button').on('click', function() {
                $('#csv-file').click();
            });
            
            $('#csv-file').on('change', function(e) {
                if (e.target.files.length > 0) {
                    self.handleFileSelect(e.target.files[0]);
                }
            });
            
            // Navigation
            $('#continue-to-mapping').on('click', function() {
                self.proceedToMapping();
            });
            
            $('#back-to-upload').on('click', function() {
                self.showStep('upload');
            });
            
            $('#back-to-configure').on('click', function() {
                self.showStep('configure');
            });
            
            $('#start-import').on('click', function() {
                self.startImport();
            });
            
            $('#new-import').on('click', function() {
                self.resetImporter();
            });
            
            // Options
            $('#identifier-field').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#identifier-custom').show();
                } else {
                    $('#identifier-custom').hide();
                }
            });
            
            // Mapping template
            $('#mapping-template').on('change', function() {
                if ($(this).val()) {
                    self.loadMappingTemplate($(this).val());
                }
            });
            
            $('#save-mapping').on('click', function() {
                self.saveMappingTemplate();
            });
        },
        
        // Initialize drag and drop
        initDragDrop: function() {
            var self = this;
            var uploadArea = $('#upload-area');
            
            uploadArea.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            uploadArea.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            uploadArea.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.handleFileSelect(files[0]);
                }
            });
            
            uploadArea.on('click', function(e) {
                if (!$(e.target).is('#browse-button')) {
                    $('#csv-file').click();
                }
            });
        },
        
        // Handle file selection
        handleFileSelect: function(file) {
            var self = this;
            
            // Validate file type
            if (!file.name.match(/\.csv$/i)) {
                alert(starcast_csv.strings.error + ': Please select a CSV file');
                return;
            }
            
            this.file = file;
            
            // Show file info
            $('#file-name').text(file.name);
            $('#file-size').text(this.formatFileSize(file.size));
            $('#file-rows').html('<span class="spinner"></span>');
            $('#file-info').show();
            
            // Upload file
            this.uploadFile();
        },
        
        // Upload file
        uploadFile: function() {
            var self = this;
            var formData = new FormData();
            
            formData.append('action', 'starcast_csv_upload');
            formData.append('nonce', starcast_csv.nonce);
            formData.append('csv_file', this.file);
            
            $.ajax({
                url: starcast_csv.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.fileInfo = response.data.file;
                        $('#file-rows').text(response.data.file.rows);
                        
                        // Store preview data
                        self.preview = response.data.preview;
                        
                        // Move to configure step
                        self.showStep('configure');
                    } else {
                        self.showError(response.data.message || starcast_csv.strings.error);
                        if (response.data.errors) {
                            response.data.errors.forEach(function(error) {
                                self.showError(error);
                            });
                        }
                    }
                },
                error: function() {
                    self.showError('Upload failed. Please try again.');
                }
            });
        },
        
        // Proceed to mapping
        proceedToMapping: function() {
            var self = this;
            
            // Validate configuration
            this.postType = $('#post-type').val();
            if (!this.postType) {
                alert('Please select a package type');
                return;
            }
            
            // Store options
            this.options = {
                update_existing: $('#update-existing').is(':checked'),
                create_providers: $('#create-providers').is(':checked'),
                skip_duplicates: $('#skip-duplicates').is(':checked'),
                identifier_field: $('#identifier-field').val(),
                identifier_custom: $('#identifier-custom').val()
            };
            
            // Load mapping fields
            this.loadMappingFields();
        },
        
        // Load mapping fields
        loadMappingFields: function() {
            var self = this;
            
            $.post(starcast_csv.ajax_url, {
                action: 'starcast_csv_get_columns',
                nonce: starcast_csv.nonce,
                post_type: this.postType
            }, function(response) {
                if (response.success) {
                    self.buildMappingInterface(response.data);
                    self.showStep('mapping');
                } else {
                    self.showError(response.data.message || 'Failed to load mapping fields');
                }
            });
        },
        
        // Build mapping interface
        buildMappingInterface: function(data) {
            var self = this;
            
            // Build CSV preview
            this.buildCSVPreview(data.preview);
            
            // Build field mapping
            var mappingHtml = '';
            
            $.each(data.fields, function(groupKey, group) {
                mappingHtml += '<div class="mapping-group">';
                mappingHtml += '<h4>' + group.label + '</h4>';
                
                $.each(group.fields, function(fieldKey, fieldLabel) {
                    var selectId = 'map_' + groupKey + '_' + fieldKey;
                    
                    mappingHtml += '<div class="mapping-row">';
                    mappingHtml += '<div class="mapping-label">' + fieldLabel + '</div>';
                    mappingHtml += '<div class="mapping-arrow">←</div>';
                    mappingHtml += '<div class="mapping-select">';
                    mappingHtml += '<select id="' + selectId + '" data-group="' + groupKey + '" data-field="' + fieldKey + '">';
                    mappingHtml += '<option value="">-- Do not import --</option>';
                    
                    // Add CSV columns
                    $.each(data.columns, function(i, column) {
                        mappingHtml += '<option value="' + self.escapeHtml(column) + '">' + self.escapeHtml(column) + '</option>';
                    });
                    
                    mappingHtml += '</select>';
                    mappingHtml += '</div>';
                    mappingHtml += '</div>';
                });
                
                mappingHtml += '</div>';
            });
            
            $('#field-mapping').html(mappingHtml);
            
            // Load saved mappings
            if (data.saved_mappings && Object.keys(data.saved_mappings).length > 0) {
                var templateOptions = '<option value="">Select a saved mapping...</option>';
                $.each(data.saved_mappings, function(key, mapping) {
                    templateOptions += '<option value="' + key + '">' + mapping.name + '</option>';
                });
                $('#mapping-template').html(templateOptions);
                
                // Store saved mappings
                this.savedMappings = data.saved_mappings;
            }
        },
        
        // Build CSV preview
        buildCSVPreview: function(preview) {
            var html = '<table>';
            
            // Headers
            html += '<thead><tr>';
            $.each(preview.headers, function(i, header) {
                html += '<th>' + header + '</th>';
            });
            html += '</tr></thead>';
            
            // Sample rows
            html += '<tbody>';
            $.each(preview.rows, function(i, row) {
                html += '<tr>';
                $.each(preview.headers, function(j, header) {
                    html += '<td>' + (row[header] || '') + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody>';
            
            html += '</table>';
            
            $('#csv-preview').html(html);
        },
        
        // Load mapping template
        loadMappingTemplate: function(templateKey) {
            if (!this.savedMappings || !this.savedMappings[templateKey]) {
                return;
            }
            
            var mapping = this.savedMappings[templateKey].mapping;
            
            // Apply mapping
            $.each(mapping, function(group, fields) {
                $.each(fields, function(field, column) {
                    $('#map_' + group + '_' + field).val(column);
                });
            });
        },
        
        // Save mapping template
        saveMappingTemplate: function() {
            var self = this;
            var name = prompt('Enter a name for this mapping template:');
            
            if (!name) {
                return;
            }
            
            // Collect current mapping
            var mapping = this.collectMapping();
            
            $.post(starcast_csv.ajax_url, {
                action: 'starcast_csv_save_mapping',
                nonce: starcast_csv.nonce,
                post_type: this.postType,
                mapping_name: name,
                mapping: JSON.stringify(mapping)
            }, function(response) {
                if (response.success) {
                    alert('Mapping saved successfully!');
                    
                    // Update template dropdown
                    var option = '<option value="' + name + '">' + name + '</option>';
                    $('#mapping-template').append(option);
                    
                    // Update saved mappings
                    self.savedMappings = response.data.mappings;
                } else {
                    self.showError(response.data.message || 'Failed to save mapping');
                }
            });
        },
        
        // Collect mapping
        collectMapping: function() {
            var mapping = {};
            
            $('#field-mapping select').each(function() {
                var $select = $(this);
                var value = $select.val();
                
                if (value) {
                    var group = $select.data('group');
                    var field = $select.data('field');
                    
                    if (!mapping[group]) {
                        mapping[group] = {};
                    }
                    
                    mapping[group][field] = value;
                }
            });
            
            return mapping;
        },
        
        // Start import
        startImport: function() {
            var self = this;
            
            // Collect mapping
            this.mapping = this.collectMapping();
            
            // Validate mapping
            if (Object.keys(this.mapping).length === 0) {
                alert('Please map at least one field');
                return;
            }
            
            // Confirm import
            if (!confirm(starcast_csv.strings.confirm_import)) {
                return;
            }
            
            // Show import step
            this.showStep('import');
            
            // Reset stats
            $('#stat-imported').text('0');
            $('#stat-updated').text('0');
            $('#stat-skipped').text('0');
            $('#stat-errors').text('0');
            $('#import-log').empty();
            $('#import-complete').hide();
            
            // Start processing
            this.processImport(0);
        },
        
        // Process import
        processImport: function(offset) {
            var self = this;
            
            var data = {
                action: 'starcast_csv_process_batch',
                nonce: starcast_csv.nonce,
                post_type: this.postType,
                mapping: JSON.stringify(this.mapping),
                offset: offset,
                update_existing: this.options.update_existing,
                create_providers: this.options.create_providers,
                skip_duplicates: this.options.skip_duplicates,
                identifier_field: this.options.identifier_field,
                identifier_custom: this.options.identifier_custom
            };
            
            if (this.importId) {
                data.import_id = this.importId;
            }
            
            $.post(starcast_csv.ajax_url, data, function(response) {
                if (response.success) {
                    var results = response.data.results;
                    var progress = response.data.progress;
                    
                    // Store import ID
                    if (!self.importId && response.data.import_id) {
                        self.importId = response.data.import_id;
                    }
                    
                    // Update stats
                    self.updateStats(results);
                    
                    // Update progress
                    self.updateProgress(progress);
                    
                    // Log errors
                    if (results.errors && results.errors.length > 0) {
                        results.errors.forEach(function(error) {
                            self.addLogEntry(error, 'error');
                        });
                    }
                    
                    // Continue or complete
                    if (response.data.has_more) {
                        // Process next batch
                        self.processImport(results.next_offset);
                    } else {
                        // Import complete
                        self.completeImport();
                    }
                } else {
                    self.showError(response.data.message || 'Import failed');
                    self.addLogEntry('Import failed: ' + (response.data.message || 'Unknown error'), 'error');
                }
            }).fail(function() {
                self.showError('Import request failed. Please check your server settings.');
                self.addLogEntry('Import request failed', 'error');
            });
        },
        
        // Update stats
        updateStats: function(results) {
            $('#stat-imported').text(function(i, val) {
                return parseInt(val) + results.imported;
            });
            
            $('#stat-updated').text(function(i, val) {
                return parseInt(val) + results.updated;
            });
            
            $('#stat-skipped').text(function(i, val) {
                return parseInt(val) + results.skipped;
            });
            
            $('#stat-errors').text(function(i, val) {
                return parseInt(val) + results.errors.length;
            });
        },
        
        // Update progress
        updateProgress: function(progress) {
            $('#progress-fill').css('width', progress.percentage + '%');
            $('#progress-text').text(progress.percentage + '%');
            
            // Update log
            var message = starcast_csv.strings.batch_progress
                .replace('%d', progress.current)
                .replace('%d', progress.total);
            
            this.addLogEntry(message, 'info');
        },
        
        // Complete import
        completeImport: function() {
            $('#progress-fill').css('width', '100%');
            $('#progress-text').text('100% - ' + starcast_csv.strings.complete);
            
            this.addLogEntry(starcast_csv.strings.complete, 'success');
            
            $('#import-complete').show();
        },
        
        // Add log entry
        addLogEntry: function(message, type) {
            var className = 'log-entry';
            if (type === 'error') {
                className += ' log-error';
            } else if (type === 'success') {
                className += ' log-success';
            } else if (type === 'warning') {
                className += ' log-warning';
            }
            
            var entry = $('<div>').addClass(className).text('[' + this.getCurrentTime() + '] ' + message);
            $('#import-log').append(entry);
            
            // Scroll to bottom
            $('#import-log').scrollTop($('#import-log')[0].scrollHeight);
        },
        
        // Load history
        loadHistory: function() {
            var self = this;
            
            $.post(starcast_csv.ajax_url, {
                action: 'starcast_csv_get_history',
                nonce: starcast_csv.nonce,
                page: 1
            }, function(response) {
                if (response.success) {
                    self.renderHistory(response.data);
                }
            });
        },
        
        // Render history
        renderHistory: function(data) {
            var html = '';
            
            if (data.entries.length === 0) {
                html = '<tr><td colspan="9" style="text-align: center;">No import history found</td></tr>';
            } else {
                $.each(data.entries, function(i, entry) {
                    html += '<tr>';
                    html += '<td>' + entry.started_at_formatted + '</td>';
                    html += '<td>' + entry.file_name + '</td>';
                    html += '<td>' + entry.post_type + '</td>';
                    html += '<td>' + entry.imported + '</td>';
                    html += '<td>' + entry.updated + '</td>';
                    html += '<td>' + entry.skipped + '</td>';
                    html += '<td>' + entry.errors + '</td>';
                    html += '<td>' + (entry.user_name || 'Unknown') + '</td>';
                    html += '<td>';
                    
                    if (entry.status === 'completed') {
                        html += '<span class="dashicons dashicons-yes" style="color: #46b450;"></span>';
                    } else if (entry.status === 'processing') {
                        html += '<span class="spinner is-active"></span>';
                    } else {
                        html += '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span>';
                    }
                    
                    html += '</td>';
                    html += '</tr>';
                });
            }
            
            $('#history-table-body').html(html);
        },
        
        // Reset importer
        resetImporter: function() {
            this.file = null;
            this.fileInfo = null;
            this.postType = null;
            this.mapping = {};
            this.options = {};
            this.importId = null;
            
            $('#csv-file').val('');
            $('#file-info').hide();
            $('#post-type').val('');
            $('#identifier-field').val('title');
            $('#identifier-custom').hide();
            
            this.showStep('upload');
        },
        
        // Show step
        showStep: function(step) {
            $('.starcast-csv-step').hide();
            $('#step-' + step).show();
        },
        
        // Show error
        showError: function(message) {
            var notice = $('<div>').addClass('notice notice-error is-dismissible');
            notice.html('<p>' + message + '</p>');
            $('.wrap > h1').after(notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut();
            }, 5000);
        },
        
        // Utility functions
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        getCurrentTime: function() {
            var now = new Date();
            return now.getHours().toString().padStart(2, '0') + ':' +
                   now.getMinutes().toString().padStart(2, '0') + ':' +
                   now.getSeconds().toString().padStart(2, '0');
        },
        
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    // Initialize when ready
    $(document).ready(function() {
        StarcastCSVImporter.init();
    });
    
})(jQuery);