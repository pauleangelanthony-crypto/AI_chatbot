<?php

class Boat_Chatbot_Database_Manager
{
    private $db_connection;
    private $cache_group = 'boat_chatbot_db';
    private $cache_expiration = 300;  // 5 minutes
    // Whitelist of allowed table names (prevent SQL injection via table name)
    // Note: Table names are case-insensitive in MySQL, so we store lowercase
    private $allowed_tables = array('api_vessels', 'api_gallery');

    // Whitelist of allowed field names (prevent SQL injection via field names)
    private $allowed_fields_list = array(
        'ID', 'Condition', 'ListingOwnerID', 'ListingOwnerName', 'ListingOwnerCell', 'ListingOwnerPhone', 'ListingOwnerEmail', 'ListingOwnerBrokerageID', 'ListingOwnerBrokerageName', 'ListingOwnerOfficeID', 'ListingOwnerOfficeDisplayPicture', 'SecondaryListingOwnerID', 'SecondaryListingOwnerName', 'SecondaryListingOwnerBrokerageID', 'SecondaryListingOwnerBrokerageName', 'SecondaryListingOwnerCell', 'SecondaryListingOwnerPhone', 'SecondaryListingOwnerEmail', 'SecondaryListingOwnerOfficeID', 'SecondaryListingOwnerOfficeDisplayPicture', 'ThirdListingOwnerID', 'ThirdListingOwnerName', 'ThirdListingOwnerCell', 'ThirdListingOwnerPhone', 'ThirdListingOwnerEmail', 'ThirdListingOwnerBrokerageID', 'ThirdListingOwnerBrokerageName', 'ThirdListingOwnerOfficeID', 'ThirdListingOwnerOfficeDisplayPicture', 'Type_', 'Status', 'ListingDate', 'Model', 'VesselName', 'PriceUSD', 'PriceEuro', 'PriceCAD', 'PriceGBP', 'MSRP', 'LOAFeet', 'LOAInch', 'LOAMeters', 'DisplayLengthFeet', 'DisplayLengthMeters', 'City', 'Zip', 'Country', 'State', 'Manufacturer', 'Category', 'Subcategory', 'Stabilizers', 'StabilizerBrand', 'Elevator', 'OfficialNumber', 'TenderRegistration', 'TenderTitle', 'TenderHIN', 'ElevatorDecks', 'Trailer', 'TrailerDesignation', 'TrailerType', 'TrailerManufacturer', 'TrailerBrand', 'TrailerYear', 'TrailerModel', 'TrailerSerialNo', 'TrailerAxels', 'SeaKeeper', 'Year', 'ShareType', 'FuelType', 'Currency', 'CurrencySymbol', 'PriceHidden', 'DocumentedYear', 'RefitYear', 'HullIdentificationNumber', 'StockNumber', 'Classification', 'NextMajorClassInspectionDate', 'Tower', 'FractionalSharesAvailable', 'Designer', 'Builder', 'InteriorDesigner', 'ExteriorDesigner', 'NavalDesigner', 'InteriorColor', 'ExteriorColor', 'Helideck', 'Flag', 'RegistryPort', 'HullMaterial', 'HullFinish', 'HullShape', 'HullWarranty', 'HullWarrantyDate', 'SuperStructureMaterial', 'SpecialOffer', 'FactoryDemo', 'ArrivingSoon', 'ConfigureToOrder', 'MaximumSpeed', 'CruiseSpeed', 'SpeedUnit', 'RangeNMI', 'MaximumDraftFeet', 'MaximumDraftInch', 'MaximumDraftMeters', 'MinimumDraftFeet', 'MinimumDraftInch', 'MinimumDraftMeters', 'BeamFeet', 'BeamInch', 'BeamMeters', 'LODFeet', 'LODInch', 'LODMeters', 'BridgeClearanceFeet', 'BridgeClearanceInch', 'LWLFeet', 'LWLInch', 'LWLMeters', 'CabinCount', 'SleepCount', 'FullBeamMaster', 'OnDeckMaster', 'SingleBerthCount', 'DoubleBerthCount', 'QueenBerthCount', 'KingBerthCount', 'TwinBerthCount', 'VBerthCount', 'PullmanQty', 'ConvertibleQty', 'SeatingCapacity', 'MaxPassengers', 'HeadCount', 'CrewHeadCount', 'CrewCabinCount', 'CaptainsQuarters', 'CrewSleepCount', 'CrewMessCount', 'HeadRoomFeet', 'HeadRoomInch', 'FuelTankCapacityGallons', 'FuelTankCapacityLiters', 'FreshWaterCapacityGallons', 'FreshWaterCapacityLiters', 'HoldingTankCapacityGallons', 'HoldingTankCapacityLiters', 'GrossTonnage', 'Displacement', 'DisplacementType', 'Deadrise', 'DryWeight', 'BallastWeight', 'NotableUpgrades', 'PriceHeadline', 'Description', 'Summary', 'Tenders', 'Included_Toys', 'ConceptBoat', 'CECertified', 'MCACertified', 'Imported', 'TaxStatus', 'SignedListingAgreement', 'SaleInUSWaters', 'BowThrusters', 'SternThrusters', 'DeckJacuzzi', 'GymEquipment', 'WheelchairAccessible', 'AC', 'ListingType', 'TradeIn', 'InStock', 'CreatedTimestamp', 'UpdatedTimestamp', 'EngineQty', 'GeneratorQty'
    );

    public function __construct()
    {
        $this->connect_to_database();
    }

    private function connect_to_database()
    {
        $db_host = get_option('boat_chatbot_db_host', 'localhost');
        $db_name = get_option('boat_chatbot_db_name');
        $db_user = get_option('boat_chatbot_db_user');
        $db_password = get_option('boat_chatbot_db_password');

        if (!$db_name || !$db_user) {
            return false;
        }

        try {
            $this->db_connection = new mysqli($db_host, $db_user, $db_password, $db_name);

            if ($this->db_connection->connect_error) {
                return false;
            }

            // Set charset for better performance and security
            $this->db_connection->set_charset('utf8mb4');

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate and sanitize table name against whitelist
     * Prevents SQL injection via table name manipulation
     */
    private function validate_table_name($table_name)
    {
        if (empty($table_name)) {
            return 'listings';
        }

        // Remove any dangerous characters (only allow alphanumeric, underscore, and case variations)
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);

        // Convert to lowercase for comparison (MySQL table names are case-insensitive on most systems)
        $table_lower = strtolower($sanitized);

        // Check against whitelist (case-insensitive)
        if (in_array($table_lower, array_map('strtolower', $this->allowed_tables))) {
            // Return the sanitized original (preserve case if needed, but sanitized)
            return $sanitized;
        }

        // If not in whitelist, but is a valid table name format, allow it
        // This allows admins to use custom table names while still preventing SQL injection
        // We've already sanitized it, so it's safe
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $sanitized) && strlen($sanitized) > 0 && strlen($sanitized) <= 64) {
            // Valid MySQL table name format - allow it
            return $sanitized;
        }

        // Default to 'listings' if invalid format
        return 'listings';
    }

    /**
     * Validate and sanitize field names against whitelist
     * Prevents SQL injection via field name manipulation
     * Only returns fields that are in the allowed_fields_list whitelist
     */
    private function validate_field_names($fields_string)
    {
        // Create lowercase version of whitelist for case-insensitive comparison
        $whitelist_lower = array_map('strtolower', $this->allowed_fields_list);
        $validated_fields = array();

        // If fields_string is provided, parse and validate it
        if (!empty($fields_string)) {
            // Parse the fields string
            $fields = array_map('trim', explode(',', $fields_string));

            foreach ($fields as $field) {
                // Remove any dangerous characters
                $field_clean = preg_replace('/[^a-zA-Z0-9_]/', '', $field);

                if (empty($field_clean)) {
                    continue;
                }

                // Check against whitelist (case-insensitive)
                $field_lower = strtolower($field_clean);
                if (in_array($field_lower, $whitelist_lower)) {
                    // Find the original case from whitelist to preserve exact column names
                    // This ensures we use the exact case as defined in the database schema
                    $key = array_search($field_lower, $whitelist_lower);
                    if ($key !== false) {
                        // Use the exact case from whitelist to match database column names
                        $validated_field = $this->allowed_fields_list[$key];
                        // Only add if not already in the array (prevent duplicates)
                        if (!in_array($validated_field, $validated_fields)) {
                            $validated_fields[] = $validated_field;
                        }
                    }
                }
            }
        }

        // If no validated fields, use default fields but validate them against whitelist
        if (empty($validated_fields)) {
            // Default to common fields that exist in the schema
            $default_fields = array('ID', 'VesselName', 'Type_', 'DisplayLengthFeet', 'PriceUSD', 'City', 'State', 'Description');

            // Validate each default field against whitelist
            foreach ($default_fields as $field) {
                $field_lower = strtolower($field);
                if (in_array($field_lower, $whitelist_lower)) {
                    $key = array_search($field_lower, $whitelist_lower);
                    if ($key !== false) {
                        $validated_field = $this->allowed_fields_list[$key];
                        if (!in_array($validated_field, $validated_fields)) {
                            $validated_fields[] = $validated_field;
                        }
                    }
                }
            }
        }

        // If still no validated fields (shouldn't happen, but safety check), return empty
        if (empty($validated_fields)) {
            return '';
        }

        // Wrap each field in backticks to handle reserved keywords (like Condition, Status, Type, etc.)
        return implode(', ', array_map(function ($field) {
            return "`{$field}`";
        }, $validated_fields));
    }

    /**
     * Sanitize string input for SQL queries
     * Additional layer of protection
     */
    private function sanitize_string($value, $max_length = 255)
    {
        if (!is_string($value)) {
            return '';
        }

        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Limit length
        if (strlen($value) > $max_length) {
            $value = substr($value, 0, $max_length);
        }

        // Escape for SQL
        if ($this->db_connection) {
            return $this->db_connection->real_escape_string($value);
        }

        return addslashes($value);
    }

    /**
     * Sanitize numeric input
     */
    private function sanitize_numeric($value, $min = null, $max = null)
    {
        $value = floatval($value);

        if ($min !== null && $value < $min) {
            $value = $min;
        }

        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value;
    }

    /**
     * Format parameters array as readable string for logging
     *
     * @param array $params Parameters array
     * @param string $types Parameter types string (e.g., 'ssi')
     * @return string Formatted parameter string
     */
    private function format_params_for_log($params, $types)
    {
        if (empty($params)) {
            return '[]';
        }

        $formatted = array();
        $type_chars = str_split($types);

        foreach ($params as $index => $value) {
            $type = isset($type_chars[$index]) ? $type_chars[$index] : '?';
            $type_name = $this->get_param_type_name($type);

            // Truncate long strings for readability
            if (is_string($value) && strlen($value) > 100) {
                $value_display = substr($value, 0, 100) . '... (truncated, length: ' . strlen($value) . ')';
            } else {
                $value_display = var_export($value, true);
            }

            $formatted[] = "[$index: $type_name] => $value_display";
        }

        return '[' . implode(', ', $formatted) . ']';
    }

    /**
     * Get human-readable parameter type name
     *
     * @param string $type Parameter type character
     * @return string Type name
     */
    private function get_param_type_name($type)
    {
        $types = array(
            's' => 'string',
            'i' => 'integer',
            'd' => 'double',
            'b' => 'blob'
        );

        return isset($types[$type]) ? $types[$type] : 'unknown';
    }

    public function query_listings($user_message, $limit = 5, $offset = 0)
    {
        if (!$this->db_connection) {
            return array();
        }

        // Sanitize limit and offset
        $original_limit = $limit;
        $limit = max(1, min(100, intval($limit)));  // Between 1 and 100
        $offset = max(0, intval($offset));  // Non-negative

        // Debug logging for limit
        if ($original_limit != $limit) {
            error_log('[Boat Chatbot] query_listings: Limit adjusted from ' . $original_limit . ' to ' . $limit . ' for query="' . $user_message . '"');
        } else {
            error_log('[Boat Chatbot] query_listings: Using limit=' . $limit . ', offset=' . $offset . ' for query="' . $user_message . '"');
        }

        // Check cache
        $cache_key = 'query_' . md5($user_message . '_' . $limit . '_' . $offset);
        $cached = $this->get_cache($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Get and validate table name
        $table_name_raw = get_option('boat_chatbot_db_table', 'listings');
        $table_name = $this->validate_table_name($table_name_raw);

        // Get and validate field names
        // First, try to get fields from template, otherwise use allowed_fields setting
        $template_fields = $this->get_fields_from_listing_format_template();
        if (!empty($template_fields)) {
            // Use fields from template (already validated and formatted)
            // Note: get_fields_from_listing_format_template() now ensures required scoring fields are included
            $allowed_fields = $template_fields;
        } else {
            try {
                // Fallback to allowed_fields setting
                $allowed_fields_raw = get_option('boat_chatbot_allowed_fields', 'ID, VesselName, Type_, DisplayLengthFeet, PriceUSD, City, State, Description, Manufacturer, Model, Year');
                $allowed_fields = $this->validate_field_names($allowed_fields_raw);

                // Ensure required fields for relevance scoring are included
                // Parse the validated fields string to check what's included
                $fields_array = array();
                if (!empty($allowed_fields) && is_string($allowed_fields)) {
                    $fields_array = array_map(function ($field) {
                        return trim(str_replace('`', '', $field));
                    }, explode(',', $allowed_fields));
                    // Filter out empty strings
                    $fields_array = array_filter($fields_array, function ($field) {
                        return !empty($field) && is_string($field);
                    });
                }

                // Fields required by score_relevance() function in class-chatbot-handler.php
                $required_for_scoring = array('VesselName', 'Type_', 'Manufacturer', 'Model', 'Description', 'City', 'State', 'Country', 'Year');
                foreach ($required_for_scoring as $field) {
                    $validated = $this->get_validated_field_name($field);
                    if ($validated && !in_array($validated, $fields_array)) {
                        $fields_array[] = $validated;
                    }
                }

                // Ensure ID is included
                $id_field = $this->get_validated_field_name('ID');
                if ($id_field && !in_array($id_field, $fields_array)) {
                    array_unshift($fields_array, $id_field);
                }

                // Re-format as SQL field list
                if (!empty($fields_array)) {
                    $allowed_fields = implode(', ', array_map(function ($field) {
                        return "`{$field}`";
                    }, $fields_array));
                } else {
                    // Fallback to default fields if everything is empty
                    $default_fields = array('ID', 'VesselName', 'Type_', 'Manufacturer', 'DisplayLengthFeet', 'PriceUSD', 'City', 'State', 'Description', 'Model', 'Year', 'Country');
                    $fields_array = array();
                    foreach ($default_fields as $field) {
                        $validated = $this->get_validated_field_name($field);
                        if ($validated) {
                            $fields_array[] = $validated;
                        }
                    }
                    if (!empty($fields_array)) {
                        $allowed_fields = implode(', ', array_map(function ($field) {
                            return "`{$field}`";
                        }, $fields_array));
                    } else {
                        // Ultimate fallback
                        $allowed_fields = '`ID`';
                    }
                }
            } catch (Exception $e) {
                // Use minimal safe default
                $allowed_fields = '`ID`';
            }
        }
        // Extract search parameters from user message
        $search_terms = $this->extract_search_terms($user_message);
        
        // Build WHERE clause using prepared statements
        $where_data = $this->build_where_clause_prepared($search_terms);

        // Build SQL query with prepared statements
        // Add LEFT JOIN to API_gallery table to get Thumbnail
        $gallery_table_raw = 'API_gallery';
        $gallery_table = $this->validate_table_name($gallery_table_raw);
        $id_field = $this->get_validated_field_name('ID');
        $id_field_safe = $id_field ? $id_field : 'ID';

        // Qualify all fields with table name to avoid ambiguity when using JOIN
        // Parse the allowed_fields string and prefix each field with table name
        $fields_list = array_map('trim', explode(',', $allowed_fields));
        $qualified_fields = array();
        foreach ($fields_list as $field) {
            // Remove backticks if present
            $field_clean = trim($field, '`');
            // Qualify with table name
            $qualified_fields[] = "`{$table_name}`.`{$field_clean}`";
        }
        $qualified_fields_str = implode(', ', $qualified_fields);

        // Use a subquery to get one thumbnail per listing to avoid duplicate rows from LEFT JOIN
        // Order by sort column ASC to get the thumbnail with the lowest sort value
        // This ensures we get unique listings when applying LIMIT
        $sql = "SELECT {$qualified_fields_str}, ";
        $sql .= "(SELECT `{$gallery_table}`.`Thumbnail` FROM `{$gallery_table}` ";
        $sql .= "WHERE `{$gallery_table}`.`vesselID` = `{$table_name}`.`{$id_field_safe}` ";
        $sql .= "ORDER BY `{$gallery_table}`.`sort` ASC ";
        $sql .= 'LIMIT 1) AS `Thumbnail` ';
        $sql .= "FROM `{$table_name}`";

        $params = array();
        $types = '';

        if (!empty($where_data['conditions'])) {
            $sql .= ' WHERE ' . $where_data['conditions'];
            $params = $where_data['params'];
            $types = $where_data['types'];
        }

        // Add ORDER BY (safe - no user input)
        // Use ID field (case-insensitive check) - qualify with table name to avoid ambiguity
        $id_field = $this->get_validated_field_name('ID');
        if ($id_field) {
            $sql .= " ORDER BY `{$table_name}`.`{$id_field}` DESC";
        } else {
            $sql .= " ORDER BY `{$table_name}`.`ID` DESC";
        }

        // Add LIMIT and OFFSET (already sanitized as integers)
        $sql .= ' LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';  // integer, integer

        // Debug logging for SQL query with limit
        error_log('[Boat Chatbot] query_listings: SQL will use LIMIT=' . $limit . ', OFFSET=' . $offset . ' for query="' . substr($user_message, 0, 100) . '"');
        try {
            // Use prepared statement
            $stmt = $this->db_connection->prepare($sql);

            if (!$stmt) {
                $error_msg = $this->db_connection->error;
                $error_code = $this->db_connection->errno;
                $params_str = $this->format_params_for_log($params, $types);
                error_log('[Boat Chatbot] query_listings - SQL Prepare Failed: ' . $error_msg . ' (Error Code: ' . $error_code . ')');
                error_log('[Boat Chatbot] query_listings - SQL Query: ' . $sql);
                error_log('[Boat Chatbot] query_listings - Parameters: ' . $params_str);
                error_log('[Boat Chatbot] query_listings - User Message: ' . $user_message);
                return array();
            }

            // Bind parameters if we have any
            // mysqli requires references, so we need to create refs array
            if (!empty($params)) {
                $refs = array();
                foreach ($params as $key => $value) {
                    $refs[$key] = &$params[$key];
                }
                $bind_result = call_user_func_array(array($stmt, 'bind_param'), array_merge(array($types), $refs));

                if (!$bind_result) {
                    $error_msg = $stmt->error;
                    $error_code = $stmt->errno;
                    $params_str = $this->format_params_for_log($params, $types);
                    error_log('[Boat Chatbot] query_listings - SQL Bind Param Failed: ' . $error_msg . ' (Error Code: ' . $error_code . ')');
                    error_log('[Boat Chatbot] query_listings - SQL Query: ' . $sql);
                    error_log('[Boat Chatbot] query_listings - Parameters: ' . $params_str);
                    $stmt->close();
                    return array();
                }
            }

            // Execute query
            if (!$stmt->execute()) {
                $error_msg = $stmt->error;
                $error_code = $stmt->errno;
                $params_str = $this->format_params_for_log($params, $types);
                error_log('[Boat Chatbot] query_listings - SQL Execute Failed: ' . $error_msg . ' (Error Code: ' . $error_code . ')');
                error_log('[Boat Chatbot] query_listings - SQL Query: ' . $sql);
                error_log('[Boat Chatbot] query_listings - Parameters: ' . $params_str);
                error_log('[Boat Chatbot] query_listings - User Message: ' . $user_message);
                $stmt->close();
                return array();
            }

            // Debug: Log the actual SQL with parameters substituted for verification
            $sql_debug = $sql;
            foreach ($params as $idx => $param) {
                $sql_debug = preg_replace('/\?/', is_numeric($param) ? $param : "'" . addslashes($param) . "'", $sql_debug, 1);
            }
            error_log('[Boat Chatbot] query_listings: Executing SQL with substituted params: ' . $sql_debug);

            // Get results
            $result = $stmt->get_result();
            if (!$result) {
                $error_msg = $this->db_connection->error;
                $error_code = $this->db_connection->errno;
                error_log('[Boat Chatbot] query_listings - Get Result Failed: ' . $error_msg . ' (Error Code: ' . $error_code . ')');
                $stmt->close();
                return array();
            }

            $listings = array();

            while ($row = $result->fetch_object()) {
                $listings[] = $row;
            }

            $stmt->close();

            // Debug logging for actual results returned
            error_log('[Boat Chatbot] query_listings: SQL executed successfully. Returned ' . count($listings) . ' listings (expected ' . $limit . ')');

            // Cache the results
            $this->set_cache($cache_key, $listings);

            return $listings;
        } catch (Exception $e) {
            $params_str = $this->format_params_for_log($params, $types);
            error_log('[Boat Chatbot] query_listings - Exception: ' . $e->getMessage());
            error_log('[Boat Chatbot] query_listings - Exception Trace: ' . $e->getTraceAsString());
            error_log('[Boat Chatbot] query_listings - SQL Query: ' . $sql);
            error_log('[Boat Chatbot] query_listings - Parameters: ' . $params_str);
            error_log('[Boat Chatbot] query_listings - User Message: ' . $user_message);
            return array();
        }
    }

    public function get_total_count($user_message)
    {
        if (!$this->db_connection) {
            return 0;
        }

        // Check cache for count
        $cache_key = 'count_' . md5($user_message);
        $cached = $this->get_cache($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Get and validate table name
        $table_name_raw = get_option('boat_chatbot_db_table', 'listings');
        $table_name = $this->validate_table_name($table_name_raw);

        $search_terms = $this->extract_search_terms($user_message);

        // Build WHERE clause - always use same logic as query_listings for consistency
        // The "show_all" flag is handled by returning empty WHERE clause when no specific filters are present
        $where_data = $this->build_where_clause_prepared($search_terms);

        $sql = "SELECT COUNT(*) as total FROM `{$table_name}`";
        $params = array();
        $types = '';

        if (!empty($where_data['conditions'])) {
            $sql .= ' WHERE ' . $where_data['conditions'];
            $params = $where_data['params'];
            $types = $where_data['types'];
        }

        // Debug logging
        $params_str = $this->format_params_for_log($params, $types);
        $search_terms_str = !empty($search_terms) ? json_encode($search_terms) : '[]';
        error_log('[Boat Chatbot] get_total_count: Query="' . $user_message . '", SQL="' . $sql . '", Params=' . $params_str . ', SearchTerms=' . $search_terms_str . ', ShowAll=' . (isset($search_terms['show_all']) ? 'true' : 'false'));

        try {
            // Use prepared statement
            $stmt = $this->db_connection->prepare($sql);

            if (!$stmt) {
                $error_msg = $this->db_connection->error;
                $error_code = $this->db_connection->errno;
                $params_str = $this->format_params_for_log($params, $types);
                error_log('[Boat Chatbot] get_total_count - SQL Prepare Failed: ' . $error_msg . ' (Error Code: ' . $error_code . ')');
                error_log('[Boat Chatbot] get_total_count - SQL Query: ' . $sql);
                error_log('[Boat Chatbot] get_total_count - Parameters: ' . $params_str);
                error_log('[Boat Chatbot] get_total_count - User Message: ' . $user_message);
                return 0;
            }

            // Bind parameters if we have any
            // mysqli requires references, so we need to create refs array
            if (!empty($params)) {
                $refs = array();
                foreach ($params as $key => $value) {
                    $refs[$key] = &$params[$key];
                }
                $bind_result = call_user_func_array(array($stmt, 'bind_param'), array_merge(array($types), $refs));

                if (!$bind_result) {
                    $error_msg = $stmt->error;
                    $error_code = $stmt->errno;
                    $params_str = $this->format_params_for_log($params, $types);
                    error_log('[Boat Chatbot] get_total_count - SQL Bind Param Failed: ' . $error_msg . ' (Error Code: ' . $error_code . ')');
                    error_log('[Boat Chatbot] get_total_count - SQL Query: ' . $sql);
                    error_log('[Boat Chatbot] get_total_count - Parameters: ' . $params_str);
                    $stmt->close();
                    return 0;
                }
            }

            // Execute query
            if (!$stmt->execute()) {
                $error_msg = $stmt->error;
                $error_code = $stmt->errno;
                $params_str = $this->format_params_for_log($params, $types);
                error_log('[Boat Chatbot] get_total_count - SQL Execute Failed: ' . $error_msg . ' (Error Code: ' . $error_code . ')');
                error_log('[Boat Chatbot] get_total_count - SQL Query: ' . $sql);
                error_log('[Boat Chatbot] get_total_count - Parameters: ' . $params_str);
                error_log('[Boat Chatbot] get_total_count - User Message: ' . $user_message);
                $stmt->close();
                return 0;
            }

            // Get result
            $result = $stmt->get_result();
            if (!$result) {
                $error_msg = $this->db_connection->error;
                $error_code = $this->db_connection->errno;
                error_log('[Boat Chatbot] get_total_count - Get Result Failed: ' . $error_msg . ' (Error Code: ' . $error_code . ')');
                $stmt->close();
                return 0;
            }

            $row = $result->fetch_object();
            if (!$row) {
                error_log('[Boat Chatbot] get_total_count - No row returned from query');
                error_log('[Boat Chatbot] get_total_count - SQL Query: ' . $sql);
                $stmt->close();
                return 0;
            }

            $total = intval($row->total);

            $stmt->close();

            // Debug logging
            error_log('[Boat Chatbot] get_total_count: Result=' . $total . ' for query="' . $user_message . '"');

            // Cache the count
            $this->set_cache($cache_key, $total);

            return $total;
        } catch (Exception $e) {
            $params_str = $this->format_params_for_log($params, $types);
            error_log('[Boat Chatbot] get_total_count - Exception: ' . $e->getMessage());
            error_log('[Boat Chatbot] get_total_count - Exception Trace: ' . $e->getTraceAsString());
            error_log('[Boat Chatbot] get_total_count - SQL Query: ' . $sql);
            error_log('[Boat Chatbot] get_total_count - Parameters: ' . $params_str);
            error_log('[Boat Chatbot] get_total_count - User Message: ' . $user_message);
            return 0;
        }
    }

    /**
     * Normalize unit abbreviations in numeric strings
     * Converts abbreviations like "100k" to "100000", "2.5m" to "2500000", etc.
     *
     * @param string $text Text containing numeric values with unit abbreviations
     * @return string Normalized text with expanded numbers
     */
    private function normalize_unit_abbreviations($text)
    {
        // Normalize thousand abbreviations (k, K, thousand, thousands)
        $text = preg_replace_callback('/(\d+(?:\.\d+)?)\s*([kK]|thousand|thousands)\b/i', function ($matches) {
            $number = floatval($matches[1]);
            return strval($number * 1000);
        }, $text);

        // Normalize million abbreviations (m, M, million, millions)
        $text = preg_replace_callback('/(\d+(?:\.\d+)?)\s*([mM]|million|millions)\b/i', function ($matches) {
            $number = floatval($matches[1]);
            return strval($number * 1000000);
        }, $text);

        // Normalize billion abbreviations (b, B, billion, billions)
        $text = preg_replace_callback('/(\d+(?:\.\d+)?)\s*([bB]|billion|billions)\b/i', function ($matches) {
            $number = floatval($matches[1]);
            return strval($number * 1000000000);
        }, $text);

        return $text;
    }

    /**
     * Extract the number of items requested by user from query
     * Examples: "show me 10 boats", "I want 5 yachts", "give me 3 listings"
     *
     * @param string $message User query message
     * @return int|null Number of items requested, or null if not specified
     */
    public function extract_item_count($message)
    {
        if (!is_string($message)) {
            return null;
        }

        $message_lower = strtolower($message);

        // Patterns to match: "show me X", "I want X", "give me X", "X boats/yachts/listings", etc.
        $patterns = array(
            '/show\s+me\s+(\d+)\s+(?:boats?|yachts?|listings?|items?|results?)/i',
            '/I\s+want\s+(\d+)\s+(?:boats?|yachts?|listings?|items?|results?)/i',
            '/give\s+me\s+(\d+)\s+(?:boats?|yachts?|listings?|items?|results?)/i',
            '/find\s+me\s+(\d+)\s+(?:boats?|yachts?|listings?|items?|results?)/i',
            '/get\s+me\s+(\d+)\s+(?:boats?|yachts?|listings?|items?|results?)/i',
            '/display\s+(\d+)\s+(?:boats?|yachts?|listings?|items?|results?)/i',
            '/(\d+)\s+(?:boats?|yachts?|listings?|items?|results?)\s+(?:please|pls|now)/i',
            '/\b(\d+)\s+(?:boats?|yachts?|listings?|items?|results?)\b/i'
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $count = intval($matches[1]);
                // Sanitize: between 1 and 100
                if ($count >= 1 && $count <= 100) {
                    return $count;
                }
            }
        }

        return null;
    }

    public function extract_search_terms($message)
    {
        $terms = array();

        // Sanitize input message
        if (!is_string($message)) {
            return $terms;
        }

        // Limit message length to prevent DoS
        $message_original = substr($message, 0, 1000);

        // Normalize unit abbreviations before processing (e.g., "100k" -> "100000", "$50k" -> "$50000")
        $message_original = $this->normalize_unit_abbreviations($message_original);

        $message = strtolower($message_original);

        // Special handling for "all" keyword
        // If query contains "all" as a standalone word, it means return everything
        // Examples: "show me all boats", "show all", "all listings"
        // We'll still extract type if specified (e.g., "all boats" = all boats of that type)
        // But we won't apply restrictive filters
        $has_all_keyword = preg_match('/\ball\b/i', $message_original);

        // Expanded boat types with synonyms and regex matching
        $boat_types = array(
            'sailboat' => array('sailboat', 'sail', 'sailing', 'sail boat', 'sailboat', 'sail yacht', 'sailing yacht', 'sloop', 'cutter', 'ketch', 'schooner'),
            'powerboat' => array('powerboat', 'power', 'motorboat', 'motor boat', 'power boat', 'motor yacht', 'motorized'),
            'yacht' => array('yacht', 'luxury', 'luxury yacht', 'superyacht', 'megayacht', 'luxury boat'),
            'fishing' => array('fishing', 'fisher', 'fishing boat', 'fishing vessel', 'sportfish', 'sport fish', 'trawler'),
            'pontoon' => array('pontoon', 'party boat', 'deck boat', 'pontoon boat'),
            'catamaran' => array('catamaran', 'cat', 'multi-hull', 'multihull', 'trimaran'),
            'speedboat' => array('speedboat', 'speed boat', 'racer', 'racing', 'race boat', 'go fast'),
            'cruiser' => array('cruiser', 'cruising', 'cruise', 'cruiser boat', 'express cruiser'),
            'trawler' => array('trawler', 'trawler yacht', 'long range cruiser'),
            'houseboat' => array('houseboat', 'house boat', 'floating home'),
            'dinghy' => array('dinghy', 'dingy', 'tender', 'small boat'),
            'jet ski' => array('jet ski', 'jetski', 'personal watercraft', 'pwc', 'wave runner', 'seadoo')
        );

        // Enhanced boat type matching with regex for better pattern recognition
        foreach ($boat_types as $type_key => $synonyms) {
            // Try exact word boundary matching first (more precise)
            foreach ($synonyms as $synonym) {
                // Use word boundaries to avoid partial matches (e.g., "sailing" in "sailboat")
                if (preg_match('/\b' . preg_quote($synonym, '/') . '\b/i', $message)) {
                    $terms['type'] = $type_key;
                    break 2;
                }
            }
        }

        // Enhanced location matching with regex patterns
        $common_locations = array(
            'seattle' => array('seattle', 'wa', 'washington', 'puget sound', 'pacific northwest'),
            'florida' => array('miami', 'fl', 'florida', 'south beach', 'miami beach', 'biscayne'),
            'san diego' => array('san diego', 'sd', 'california', 'san diego bay', 'coronado'),
            'san francisco' => array('san francisco', 'sf', 'bay area', 'san fran', 'sf bay', 'marin'),
            'boston' => array('boston', 'ma', 'massachusetts', 'cape cod', 'new england'),
            'fort lauderdale' => array('fort lauderdale', 'ft lauderdale', 'lauderdale', 'broward'),
            'new york' => array('new york', 'ny', 'nyc', 'manhattan', 'long island', 'hamptons'),
            'los angeles' => array('los angeles', 'la', 'california', 'marina del rey', 'newport beach'),
            'chicago' => array('chicago', 'il', 'illinois', 'lake michigan'),
            'naples' => array('naples', 'sw florida', 'southwest florida'),
            'annapolis' => array('annapolis', 'md', 'maryland', 'chesapeake'),
            'san juan' => array('san juan', 'puerto rico', 'pr', 'caribbean')
        );

        // Enhanced location matching with word boundaries
        foreach ($common_locations as $location_key => $variants) {
            foreach ($variants as $variant) {
                // Use word boundaries for better matching
                if (preg_match('/\b' . preg_quote($variant, '/') . '\b/i', $message)) {
                    $terms['location'] = $location_key;
                    break 2;
                }
            }
        }

        // Enhanced price extraction with comprehensive regex patterns
        // Pattern 1: "under $X" or "under X dollars"
        if (preg_match('/under\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:dollars?|usd)?/i', $message, $matches)) {
            $price = floatval(str_replace(',', '', $matches[1]));
            if ($price >= 1000) {
                $terms['max_price'] = $this->sanitize_numeric($price, min: 1000);
            }
        }
        // Pattern 2: "less than $X" or "less than X"
        elseif (preg_match('/less\s+than\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:dollars?|usd)?/i', $message, $matches)) {
            $price = floatval(str_replace(',', '', $matches[1]));
            if ($price >= 1000) {
                $terms['max_price'] = $this->sanitize_numeric($price, min: 1000);
            }
        }
        // Pattern 3: "max $X" or "maximum $X"
        elseif (preg_match('/max(?:imum)?\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:dollars?|usd)?/i', $message, $matches)) {
            $price = floatval(str_replace(',', '', $matches[1]));
            if ($price >= 1000) {
                $terms['max_price'] = $this->sanitize_numeric($price, min: 1000);
            }
        }
        // Pattern 4: "up to $X" or "up to X"
        elseif (preg_match('/up\s+to\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:dollars?|usd)?/i', $message, $matches)) {
            $price = floatval(str_replace(',', '', $matches[1]));
            if ($price >= 1000) {
                $terms['max_price'] = $this->sanitize_numeric($price, min: 1000);
            }
        }
        // Pattern 5: "over $X" or "more than $X" (min price)
        elseif (preg_match('/(?:over|more\s+than|at\s+least|minimum|min)\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:dollars?|usd)?/i', $message, $matches)) {
            $price = floatval(str_replace(',', '', $matches[1]));
            if ($price >= 1000) {
                $terms['min_price'] = $this->sanitize_numeric($price, min: 1000);
            }
        }
        // Pattern 6: Price range "$X to $Y" or "$X-$Y"
        elseif (preg_match('/\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:to|-|and)\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:dollars?|usd)?/i', $message, $matches)) {
            $min_price = floatval(str_replace(',', '', $matches[1]));
            $max_price = floatval(str_replace(',', '', $matches[2]));
            // Ensure min < max
            if ($min_price < $max_price) {
                if ($min_price >= 1000) {
                    $terms['min_price'] = $this->sanitize_numeric($min_price, min: 1000);
                }
                if ($max_price >= 1000) {
                    $terms['max_price'] = $this->sanitize_numeric($max_price, min: 1000);
                }
            } else {
                if ($max_price >= 1000) {
                    $terms['min_price'] = $this->sanitize_numeric($max_price, min: 1000);
                }
                if ($min_price >= 1000) {
                    $terms['max_price'] = $this->sanitize_numeric($min_price, min: 1000);
                }
            }
        }
        // Pattern 6.5: Price mentioned with budget/price/cost without direction words (treat as "under")
        // Examples: "budget is 200k", "price is 500", "cost is 1000", "my budget is 200k"
        elseif (preg_match('/\b(?:my\s+)?(?:budget|price|cost)\s+(?:is|of|at)?\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:dollars?|usd)?/i', $message, $matches) && empty($terms['max_price']) && empty($terms['min_price'])) {
            $price = floatval(str_replace(',', '', $matches[1]));
            // Only use if it's a reasonable price (not just a year or other number)
            if ($price >= 1000 && $price <= 100000000) {
                $terms['max_price'] = $this->sanitize_numeric($price, min: 1000);
            }
        }
        // Pattern 7: Standalone price "$X" (treat as max price if no other context)
        elseif (preg_match('/\b\$(\d+(?:,\d{3})*(?:\.\d{2})?)\b/i', $message, $matches) && empty($terms['max_price']) && empty($terms['min_price'])) {
            $price = floatval(str_replace(',', '', $matches[1]));
            // Only use if it's a reasonable price (not just a year or other number)
            if ($price >= 1000 && $price <= 100000000) {
                if ($price >= 1000) {
                    $terms['max_price'] = $this->sanitize_numeric($price, min: 1000);
                }
            }
        }

        // Enhanced "between" keyword parsing for ranges
        // Handle "between X and Y" or "between X-Y" patterns for different types

        // Pattern: "between X-Y ft" or "between X and Y feet" (length)
        if (preg_match('/\bbetween\s+(\d+(?:\.\d+)?)\s*(?:and|-|to)\s*(\d+(?:\.\d+)?)\s*(?:ft|foot|feet|meter|metre|m|meters|metres)\b/i', $message, $matches)) {
            $min_val = floatval($matches[1]);
            $max_val = floatval($matches[2]);
            // Check if it's meters (look for meter/m in the match)
            if (preg_match('/meter|metre|m\b/i', $message)) {
                $min_val *= 3.28084;
                $max_val *= 3.28084;
            }
            // Ensure min < max and valid range
            if ($min_val < $max_val && $min_val >= 1 && $max_val <= 1000) {
                $terms['min_length'] = $this->sanitize_numeric($min_val, 1, 1000);
                $terms['max_length'] = $this->sanitize_numeric($max_val, 1, 1000);
            } elseif ($min_val > $max_val && $max_val >= 1 && $min_val <= 1000) {
                // Handle case where numbers are in wrong order
                $terms['min_length'] = $this->sanitize_numeric($max_val, 1, 1000);
                $terms['max_length'] = $this->sanitize_numeric($min_val, 1, 1000);
            }
        }
        // Pattern: "between $X and $Y" or "between $Xk-$Yk" (price)
        elseif (preg_match('/\bbetween\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:and|-|to)\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:k|dollars?|usd)?/i', $message, $matches)) {
            $min_val = floatval(str_replace(',', '', $matches[1]));
            $max_val = floatval(str_replace(',', '', $matches[2]));
            // Handle 'k' multiplier (1000)
            if (preg_match('/\bk\b/i', $message)) {
                $min_val *= 1000;
                $max_val *= 1000;
            }
            // Ensure min < max and reasonable price range
            if ($min_val < $max_val && $min_val >= 1000 && $max_val <= 100000000) {
                $terms['min_price'] = $this->sanitize_numeric($min_val, min: 1000);
                $terms['max_price'] = $this->sanitize_numeric($max_val, min: 1000);
            } elseif ($min_val > $max_val && $max_val >= 1000 && $min_val <= 100000000) {
                // Handle case where numbers are in wrong order
                $terms['min_price'] = $this->sanitize_numeric($max_val, min: 1000);
                $terms['max_price'] = $this->sanitize_numeric($min_val, min: 1000);
            }
        }
        // Pattern: "between X and Y" (year - 4 digit years)
        elseif (preg_match('/\bbetween\s+(19\d{2}|20[0-9]{2})\s*(?:and|-|to)\s*(19\d{2}|20[0-9]{2})\b/i', $message, $matches)) {
            $min_year = intval($matches[1]);
            $max_year = intval($matches[2]);
            // Ensure valid year range
            if ($min_year >= 1900 && $max_year <= date('Y') + 1) {
                if ($min_year < $max_year) {
                    $terms['min_year'] = $min_year;
                    $terms['max_year'] = $max_year;
                } else {
                    // Handle case where years are in wrong order
                    $terms['min_year'] = $max_year;
                    $terms['max_year'] = $min_year;
                }
            }
        }

        // Enhanced length extraction with comprehensive regex patterns
        // Pattern 1: "X feet" or "X ft" or "X'"
        if (preg_match("/(\d+(?:\.\d+)?)\s*(?:ft|foot|feet|'|feet\s+long|ft\s+long)\b/i", $message, $matches)) {
            $length = floatval($matches[1]);
            if ($length >= 1 && $length <= 1000) {
                $terms['length'] = $this->sanitize_numeric($length, 1, 1000);
            }
        }
        // Pattern 2: "X meters" or "X m"
        elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:meter|metre|m|meters|metres)\b/i', $message, $matches)) {
            // Convert meters to feet (1 meter = 3.28084 feet)
            $length = floatval($matches[1]) * 3.28084;
            if ($length >= 1 && $length <= 1000) {
                $terms['length'] = $this->sanitize_numeric($length, 1, 1000);
            }
        }
        // Pattern 3: Length range "X to Y feet" or "X-Y feet"
        elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:to|-|and)\s*(\d+(?:\.\d+)?)\s*(?:ft|foot|feet|meter|metre|m)\b/i', $message, $matches)) {
            $min_length = floatval($matches[1]);
            $max_length = floatval($matches[2]);
            // Check if it's meters (look for meter/m in the match)
            if (preg_match('/meter|metre|m\b/i', $message)) {
                $min_length *= 3.28084;
                $max_length *= 3.28084;
            }
            // Ensure min < max
            if ($min_length < $max_length) {
                if ($min_length >= 1 && $min_length <= 1000) {
                    $terms['min_length'] = $this->sanitize_numeric($min_length, 1, 1000);
                }
                if ($max_length >= 1 && $max_length <= 1000) {
                    $terms['max_length'] = $this->sanitize_numeric($max_length, 1, 1000);
                }
            } else {
                if ($min_length >= 1 && $min_length <= 1000) {
                    $terms['min_length'] = $this->sanitize_numeric($min_length, 1, 1000);
                }
                if ($max_length >= 1 && $max_length <= 1000) {
                    $terms['max_length'] = $this->sanitize_numeric($max_length, 1, 1000);
                }
            }
        }

        // Extract manufacturer/brand using regex
        // Common boat manufacturers
        $manufacturers = array('beneteau', 'catalina', 'hunter', 'pearson', "o'day", 'c&c', 'jeanneau', 'bavaria',
            'hunter', 'sailboat', 'hans christian', 'valiant', 'tartan', 'cabo', 'bertram',
            'hatteras', 'viking', 'ocean', 'azimut', 'ferretti', 'princess', 'sunseeker',
            'pershing', 'riva', 'lurssen', 'feadship', 'heesen', 'amels', 'westport',
            'palmer johnson', 'trinity', 'benetti', 'lazzara', 'marquis', 'sea ray',
            'regal', 'cobalt', 'formula', 'cigarette', 'fountain', 'donzi', 'scarab',
            'everglades');

        foreach ($manufacturers as $manufacturer) {
            // Use word boundaries for exact manufacturer matching
            if (preg_match('/\b' . preg_quote($manufacturer, '/') . '\b/i', $message)) {
                $terms['manufacturer'] = $manufacturer;
                break;
            }
        }

        // Extract year using regex (4-digit years between 1900-2100)
        // Also handle "X and newer" or "X and newer" patterns
        if (preg_match('/\b(19\d{2}|20[0-9]{2})\s+(?:and\s+)?(?:newer|later|up|onwards)\b/i', $message, $matches)) {
            $min_year = intval($matches[1]);
            if ($min_year >= 1900 && $min_year <= date('Y') + 1) {
                $terms['min_year'] = $min_year;
                $terms['max_year'] = date('Y') + 1;  // Current year + 1 for new boats
            }
        } elseif (preg_match('/\b(19\d{2}|20[0-9]{2})\b/', $message, $matches)) {
            $year = intval($matches[1]);
            // Only use if it's a reasonable boat year (not a price or other number)
            if ($year >= 1900 && $year <= date('Y') + 1) {
                $terms['year'] = $year;
            }
        }
        // Year range "X to Y" or "X-Y"
        if (preg_match('/\b(19\d{2}|20[0-9]{2})\s*(?:to|-|and)\s*(19\d{2}|20[0-9]{2})\b/', $message, $matches)) {
            $min_year = intval($matches[1]);
            $max_year = intval($matches[2]);
            if ($min_year >= 1900 && $max_year <= date('Y') + 1) {
                if ($min_year < $max_year) {
                    $terms['min_year'] = $min_year;
                    $terms['max_year'] = $max_year;
                } else {
                    $terms['min_year'] = $max_year;
                    $terms['max_year'] = $min_year;
                }
            }
        }

        // Extract category (e.g., Center Console, Walkaround, etc.)
        $categories = array(
            'center console' => array('center console', 'centerconsole', 'cc', 'center console boat'),
            'walkaround' => array('walkaround', 'walk around', 'walk-around'),
            'cuddy cabin' => array('cuddy cabin', 'cuddy', 'cuddycab'),
            'express' => array('express', 'express cruiser', 'express boat'),
            'sportfish' => array('sportfish', 'sport fish', 'sportfishing', 'sport fishing'),
            'convertible' => array('convertible', 'convertible sportfish'),
            'flybridge' => array('flybridge', 'fly bridge', 'fly-bridge'),
            'trawler' => array('trawler', 'trawler yacht'),
            'catamaran' => array('catamaran', 'cat', 'multi-hull', 'multihull'),
            'sailboat' => array('sailboat', 'sail', 'sailing yacht', 'sloop', 'cutter', 'ketch'),
            'bowrider' => array('bowrider', 'bow rider', 'bow-rider'),
            'deck boat' => array('deck boat', 'deckboat'),
            'pontoon' => array('pontoon', 'pontoon boat', 'party boat')
        );

        foreach ($categories as $category_key => $synonyms) {
            foreach ($synonyms as $synonym) {
                if (preg_match('/\b' . preg_quote($synonym, '/') . '\b/i', $message)) {
                    $terms['category'] = $category_key;
                    break 2;
                }
            }
        }

        // Extract general search terms (words that might be in vessel name or description)
        // Remove common stop words and extract meaningful terms
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'from', 'up', 'about', 'into', 'through', 'during', 'including', 'under', 'over',
            'show', 'find', 'search', 'list', 'display', 'boat', 'boats', 'yacht', 'yachts',
            'vessel', 'vessels', 'i', 'want', 'need', 'looking', 'for', 'buy', 'purchase',
            'see', 'get', 'have', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'do',
            'does', 'did', 'will', 'would', 'can', 'could', 'should', 'may', 'might', 'must',
            'all', 'me', 'my', 'you', 'your', 'give', 'tell', 'please');

        // Extract words (3+ characters) that aren't stop words
        preg_match_all('/\b([a-z]{3,})\b/i', $message_original, $word_matches);
        $search_words = array();
        if (!empty($word_matches[1])) {
            foreach ($word_matches[1] as $word) {
                $word_lower = strtolower($word);
                // Skip if it's a stop word or number
                if (in_array($word_lower, $stop_words) || is_numeric($word) || strlen($word) < 3) {
                    continue;
                }

                // Skip if this word matches an already extracted specific term
                $skip_word = false;
                if (isset($terms['type']) && strpos($word_lower, $terms['type']) !== false) {
                    $skip_word = true;
                }
                if (isset($terms['location']) && strpos($word_lower, $terms['location']) !== false) {
                    $skip_word = true;
                }
                if (isset($terms['manufacturer']) && strpos($word_lower, $terms['manufacturer']) !== false) {
                    $skip_word = true;
                }

                if (!$skip_word) {
                    $search_words[] = $word;
                }
            }
        }

        // If we have meaningful search words, add them as general search terms
        if (!empty($search_words)) {
            // Limit to first 5 words to avoid overly complex queries
            $terms['general_search'] = array_slice($search_words, 0, 5);
        }

        // Special handling for "all" keyword
        // If query contains "all" as a standalone word, it means return everything
        // Examples: "show me all boats", "show all", "all listings"
        // When "all" is present, we should return all matching items without restrictive filters
        // Store this flag for use in query building
        if ($has_all_keyword) {
            $terms['show_all'] = true;
        }

        return $terms;
    }

    /**
     * Get field name mapping for search operations
     * Maps generic field names to actual database column names
     */
    private function get_search_field_mapping()
    {
        // Map generic search terms to actual database columns
        // These should match the actual column names in your database
        return array(
            'type' => 'Type_',  // Boat type column
            'location' => 'City',  // Location column (could also be State, Country)
            'price' => 'PriceUSD',  // Price column
            'length' => 'DisplayLengthFeet'  // Length column
        );
    }

    /**
     * Validate a field name exists in the whitelist and return the correct case
     */
    private function get_validated_field_name($field_name)
    {
        $field_clean = preg_replace('/[^a-zA-Z0-9_]/', '', $field_name);
        $field_lower = strtolower($field_clean);
        $whitelist_lower = array_map('strtolower', $this->allowed_fields_list);

        if (in_array($field_lower, $whitelist_lower)) {
            $key = array_search($field_lower, $whitelist_lower);
            if ($key !== false) {
                return $this->allowed_fields_list[$key];
            }
        }

        return null;
    }

    /**
     * Build WHERE clause using prepared statements
     * This is the secure way to prevent SQL injection
     */
    public function build_where_clause_prepared($search_terms)
    {
        $conditions = array();
        $params = array();
        $types = '';
        $field_mapping = $this->get_search_field_mapping();

        // Special handling for "show_all" keyword
        // If "show_all" is true and there are no specific filters (type, location, price, etc.),
        // return empty WHERE clause to get all listings
        if (!empty($search_terms['show_all'])) {
            // Check if there are any specific filters
            $has_specific_filters = !empty($search_terms['type']) ||
                !empty($search_terms['location']) ||
                !empty($search_terms['min_price']) ||
                !empty($search_terms['max_price']) ||
                !empty($search_terms['length']) ||
                !empty($search_terms['min_length']) ||
                !empty($search_terms['max_length']) ||
                !empty($search_terms['manufacturer']) ||
                !empty($search_terms['category']) ||
                !empty($search_terms['year']) ||
                !empty($search_terms['min_year']) ||
                !empty($search_terms['max_year']);

            // If no specific filters, return all listings (empty WHERE clause)
            if (!$has_specific_filters) {
                return array(
                    'conditions' => '',
                    'params' => array(),
                    'types' => ''
                );
            }
            // If there are specific filters, continue to apply them (but skip general_search)
        }

        if (!empty($search_terms['type'])) {
            // Get the correct field name for type
            $type_field = $this->get_validated_field_name($field_mapping['type']);
            if ($type_field) {
                $type = $this->sanitize_string($search_terms['type'], 50);

                // Search for the type in multiple fields: Type_, VesselName, and Description
                // This ensures we find yachts even if "yacht" is in the name or description, not just the type field
                $type_conditions = array();

                // Search in Type_ field
                $type_conditions[] = "LOWER(`{$type_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                $params[] = $type;
                $types .= 's';

                // Also search in VesselName field if available
                $vessel_name_field = $this->get_validated_field_name('VesselName');
                if ($vessel_name_field) {
                    $type_conditions[] = "LOWER(`{$vessel_name_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $type;
                    $types .= 's';
                }

                // Also search in Description field if available
                $description_field = $this->get_validated_field_name('Description');
                if ($description_field) {
                    $type_conditions[] = "LOWER(`{$description_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $type;
                    $types .= 's';
                }

                // Combine type searches with OR (any of these fields can match)
                if (count($type_conditions) > 0) {
                    $conditions[] = '(' . implode(' OR ', $type_conditions) . ')';
                }
            }
        }

        if (!empty($search_terms['location'])) {
            // Search across City, State, and Country fields to increase match possibility
            $location = $this->sanitize_string($search_terms['location'], 100);
            $location_conditions = array();

            // Get validated field names for all location fields
            $city_field = $this->get_validated_field_name('City');
            $state_field = $this->get_validated_field_name('State');
            $country_field = $this->get_validated_field_name('Country');

            // Build OR conditions for each available location field
            // Use case-insensitive LIKE with wildcards for better matching
            if ($city_field) {
                $location_conditions[] = "LOWER(`{$city_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                $params[] = $location;
                $types .= 's';
            }

            if ($state_field) {
                $location_conditions[] = "LOWER(`{$state_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                $params[] = $location;
                $types .= 's';
            }

            if ($country_field) {
                $location_conditions[] = "LOWER(`{$country_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                $params[] = $location;
                $types .= 's';
            }

            // Combine all location conditions with OR
            if (!empty($location_conditions)) {
                $conditions[] = '(' . implode(' OR ', $location_conditions) . ')';
            }
        }

        // Handle min_price
        if (!empty($search_terms['min_price'])) {
            $price_field = $this->get_validated_field_name($field_mapping['price']);
            if ($price_field) {
                $min_price = $this->sanitize_numeric($search_terms['min_price'], 0, 100000000);
                $conditions[] = "`{$price_field}` >= ?";
                $params[] = $min_price;
                $types .= 'd';  // double/float
            }
        }

        // Handle max_price
        if (!empty($search_terms['max_price'])) {
            // Get the correct field name for price
            $price_field = $this->get_validated_field_name($field_mapping['price']);
            if ($price_field) {
                $max_price = $this->sanitize_numeric($search_terms['max_price'], 0, 100000000);
                $conditions[] = "`{$price_field}` <= ?";
                $params[] = $max_price;
                $types .= 'd';  // double/float
            }
        }

        // Handle length (single value with range)
        if (!empty($search_terms['length'])) {
            // Get the correct field name for length
            $length_field = $this->get_validated_field_name($field_mapping['length']);
            if ($length_field) {
                $length = intval($search_terms['length']);
                $length_min = max(1, $length - 2);
                $length_max = min(1000, $length + 2);
                $conditions[] = "`{$length_field}` BETWEEN ? AND ?";
                $params[] = $length_min;
                $params[] = $length_max;
                $types .= 'ii';  // integer, integer
            }
        }
        // Handle length range (min_length and max_length)
        elseif (!empty($search_terms['min_length']) || !empty($search_terms['max_length'])) {
            $length_field = $this->get_validated_field_name($field_mapping['length']);
            if ($length_field) {
                if (!empty($search_terms['min_length']) && !empty($search_terms['max_length'])) {
                    // Both min and max specified
                    $min_length = $this->sanitize_numeric($search_terms['min_length'], 1, 1000);
                    $max_length = $this->sanitize_numeric($search_terms['max_length'], 1, 1000);
                    $conditions[] = "`{$length_field}` BETWEEN ? AND ?";
                    $params[] = $min_length;
                    $params[] = $max_length;
                    $types .= 'ii';
                } elseif (!empty($search_terms['min_length'])) {
                    // Only min specified
                    $min_length = $this->sanitize_numeric($search_terms['min_length'], 1, 1000);
                    $conditions[] = "`{$length_field}` >= ?";
                    $params[] = $min_length;
                    $types .= 'i';
                } elseif (!empty($search_terms['max_length'])) {
                    // Only max specified
                    $max_length = $this->sanitize_numeric($search_terms['max_length'], 1, 1000);
                    $conditions[] = "`{$length_field}` <= ?";
                    $params[] = $max_length;
                    $types .= 'i';
                }
            }
        }

        // Handle manufacturer
        if (!empty($search_terms['manufacturer'])) {
            $manufacturer_field = $this->get_validated_field_name('Manufacturer');
            if ($manufacturer_field) {
                $manufacturer = $this->sanitize_string($search_terms['manufacturer'], 100);
                // Use case-insensitive LIKE for partial matching
                $conditions[] = "LOWER(`{$manufacturer_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                $params[] = $manufacturer;
                $types .= 's';  // string
            }
        }

        // Handle category (e.g., Center Console, Walkaround, etc.)
        if (!empty($search_terms['category'])) {
            $category_field = $this->get_validated_field_name('Category');
            if ($category_field) {
                $category = $this->sanitize_string($search_terms['category'], 100);
                // Use case-insensitive LIKE for partial matching
                $conditions[] = "LOWER(`{$category_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                $params[] = $category;
                $types .= 's';  // string
            } else {
                // Fallback: search in Type_ and Description fields if Category field doesn't exist
                $type_field = $this->get_validated_field_name('Type_');
                $description_field = $this->get_validated_field_name('Description');
                $category = $this->sanitize_string($search_terms['category'], 100);
                $category_conditions = array();

                if ($type_field) {
                    $category_conditions[] = "LOWER(`{$type_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $category;
                    $types .= 's';
                }

                if ($description_field) {
                    $category_conditions[] = "LOWER(`{$description_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $category;
                    $types .= 's';
                }

                if (!empty($category_conditions)) {
                    $conditions[] = '(' . implode(' OR ', $category_conditions) . ')';
                }
            }
        }

        // Handle year (single value)
        if (!empty($search_terms['year'])) {
            $year_field = $this->get_validated_field_name('Year');
            if ($year_field) {
                $year = intval($search_terms['year']);
                if ($year >= 1900 && $year <= date('Y') + 1) {
                    $conditions[] = "`{$year_field}` = ?";
                    $params[] = $year;
                    $types .= 'i';  // integer
                }
            }
        }
        // Handle year range (min_year and max_year)
        elseif (!empty($search_terms['min_year']) || !empty($search_terms['max_year'])) {
            $year_field = $this->get_validated_field_name('Year');
            if ($year_field) {
                if (!empty($search_terms['min_year']) && !empty($search_terms['max_year'])) {
                    // Both min and max specified
                    $min_year = intval($search_terms['min_year']);
                    $max_year = intval($search_terms['max_year']);
                    if ($min_year >= 1900 && $max_year <= date('Y') + 1) {
                        $conditions[] = "`{$year_field}` BETWEEN ? AND ?";
                        $params[] = $min_year;
                        $params[] = $max_year;
                        $types .= 'ii';
                    }
                } elseif (!empty($search_terms['min_year'])) {
                    // Only min specified
                    $min_year = intval($search_terms['min_year']);
                    if ($min_year >= 1900) {
                        $conditions[] = "`{$year_field}` >= ?";
                        $params[] = $min_year;
                        $types .= 'i';
                    }
                } elseif (!empty($search_terms['max_year'])) {
                    // Only max specified
                    $max_year = intval($search_terms['max_year']);
                    if ($max_year <= date('Y') + 1) {
                        $conditions[] = "`{$year_field}` <= ?";
                        $params[] = $max_year;
                        $types .= 'i';
                    }
                }
            }
        }

        // Handle general search terms (search in vessel name, description, model)
        // Skip general_search if "all" keyword is present (to avoid restrictive filtering)
        if (!empty($search_terms['general_search']) && is_array($search_terms['general_search']) && empty($search_terms['show_all'])) {
            $general_conditions = array();

            foreach ($search_terms['general_search'] as $search_word) {
                $word = $this->sanitize_string($search_word, 100);
                if (empty($word) || strlen($word) < 2) {
                    continue;
                }

                $word_conditions = array();

                // Search in VesselName
                $vessel_name_field = $this->get_validated_field_name('VesselName');
                if ($vessel_name_field) {
                    $word_conditions[] = "LOWER(`{$vessel_name_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $word;
                    $types .= 's';
                }

                // Search in Description
                $description_field = $this->get_validated_field_name('Description');
                if ($description_field) {
                    $word_conditions[] = "LOWER(`{$description_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $word;
                    $types .= 's';
                }

                // Search in Model
                $model_field = $this->get_validated_field_name('Model');
                if ($model_field) {
                    $word_conditions[] = "LOWER(`{$model_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $word;
                    $types .= 's';
                }

                // Search in Manufacturer
                $manufacturer_field = $this->get_validated_field_name('Manufacturer');
                if ($manufacturer_field) {
                    $word_conditions[] = "LOWER(`{$manufacturer_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $word;
                    $types .= 's';
                }

                // Search in City, State, and Country (location fields)
                // This increases possibility of finding location matches
                $city_field = $this->get_validated_field_name('City');
                if ($city_field) {
                    $word_conditions[] = "LOWER(`{$city_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $word;
                    $types .= 's';
                }

                $state_field = $this->get_validated_field_name('State');
                if ($state_field) {
                    $word_conditions[] = "LOWER(`{$state_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $word;
                    $types .= 's';
                }

                $country_field = $this->get_validated_field_name('Country');
                if ($country_field) {
                    $word_conditions[] = "LOWER(`{$country_field}`) LIKE LOWER(CONCAT('%', ?, '%'))";
                    $params[] = $word;
                    $types .= 's';
                }

                // At least one field must match this word (OR within word)
                if (!empty($word_conditions)) {
                    $general_conditions[] = '(' . implode(' OR ', $word_conditions) . ')';
                }
            }

            // All words must be found somewhere (AND between words)
            if (!empty($general_conditions)) {
                $conditions[] = '(' . implode(' AND ', $general_conditions) . ')';
            }
        }

        return array(
            'conditions' => implode(' AND ', $conditions),
            'params' => $params,
            'types' => $types
        );
    }

    /**
     * Extract database fields required by the Listing Format Template
     * Reads the template from WordPress options and maps placeholders to database fields
     *
     * @return array Array of database field names that need to be selected
     */
    public function extract_fields_from_listing_format_template()
    {
        // Get the Listing Format Template from WordPress options
        $format_template = get_option('boat_chatbot_listing_format', '- {title} | {type} | {length}\' | ${price} | {location}');

        // Extract all placeholders from the template (e.g., {title}, {type}, etc.)
        preg_match_all('/\{(\w+)\}/', $format_template, $matches);
        $placeholders = !empty($matches[1]) ? array_unique($matches[1]) : array();

        // Map placeholders to actual database field names
        $placeholder_to_field = array(
            'title' => 'VesselName',
            'type' => 'Type_',
            'length' => 'DisplayLengthFeet',
            'price' => 'PriceUSD',
            'location' => 'City',  // Primary location field, State can be used as fallback
            'description' => 'Description',
            'url' => 'ID',  // ID is used to construct URLs
            'manufacturer' => 'Manufacturer',
            'model' => 'Model',
            'year' => 'Year'
        );

        // Additional location fields that might be needed
        $location_fields = array('City', 'State', 'Country');

        $required_fields = array();

        // Map each placeholder to its database field
        foreach ($placeholders as $placeholder) {
            $placeholder_lower = strtolower($placeholder);

            if (isset($placeholder_to_field[$placeholder_lower])) {
                $field_name = $placeholder_to_field[$placeholder_lower];

                // Validate the field exists in whitelist
                $validated_field = $this->get_validated_field_name($field_name);
                if ($validated_field) {
                    $required_fields[] = $validated_field;
                }

                // For location, also include State and Country as they might be used
                if ($placeholder_lower === 'location') {
                    foreach ($location_fields as $loc_field) {
                        $validated_loc_field = $this->get_validated_field_name($loc_field);
                        if ($validated_loc_field && !in_array($validated_loc_field, $required_fields)) {
                            $required_fields[] = $validated_loc_field;
                        }
                    }
                }
            }
        }

        // Always include ID if not already present (needed for URLs and identification)
        $id_field = $this->get_validated_field_name('ID');
        if ($id_field && !in_array($id_field, $required_fields)) {
            array_unshift($required_fields, $id_field);
        }

        // Remove duplicates and return
        return array_unique($required_fields);
    }

    /**
     * Get validated field names string for SQL SELECT query
     * Extracts fields from Listing Format Template and returns them as a comma-separated string
     *
     * @return string Comma-separated list of validated database fields wrapped in backticks
     */
    public function get_fields_from_listing_format_template()
    {
        try {
            $fields = $this->extract_fields_from_listing_format_template();

            // Ensure $fields is an array
            if (!is_array($fields)) {
                $fields = array();
            }

            if (empty($fields)) {
                // Default to common fields if template doesn't specify any
                $default_fields = array('ID', 'VesselName', 'Type_', 'Manufacturer', 'DisplayLengthFeet', 'PriceUSD', 'City', 'State', 'Description');
                $fields = array();
                foreach ($default_fields as $field) {
                    $validated = $this->get_validated_field_name($field);
                    if ($validated) {
                        $fields[] = $validated;
                    }
                }
            }

            // Always include fields needed for relevance scoring, even if not in template
            // These fields are required by the score_relevance() function in class-chatbot-handler.php
            $required_for_scoring = array('VesselName', 'Type_', 'Manufacturer', 'Model', 'Description', 'City', 'State', 'Country', 'Year');
            foreach ($required_for_scoring as $field) {
                $validated = $this->get_validated_field_name($field);
                if ($validated && !in_array($validated, $fields)) {
                    $fields[] = $validated;
                }
            }

            // Ensure we have at least ID field
            $id_field = $this->get_validated_field_name('ID');
            if ($id_field && !in_array($id_field, $fields)) {
                array_unshift($fields, $id_field);
            }

            // Filter out any empty values
            $fields = array_filter($fields, function ($field) {
                return !empty($field) && is_string($field);
            });

            // If still empty, use minimal default
            if (empty($fields)) {
                $fields = array('ID');
            }

            // Wrap each field in backticks to handle reserved keywords
            return implode(', ', array_map(function ($field) {
                return "`{$field}`";
            }, $fields));
        } catch (Exception $e) {
            // Return minimal safe default
            return '`ID`';
        }
    }

    public function test_connection()
    {
        if ($this->db_connection && $this->db_connection->ping()) {
            return array('success' => true, 'message' => 'Database connection successful');
        } else {
            return array('success' => false, 'message' => 'Database connection failed');
        }
    }

    // Cache helper methods
    private function get_cache($key)
    {
        // Sanitize cache key to prevent injection
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);

        // Try Redis first, fallback to transients
        if (class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
            $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
            if ($redis_manager->is_enabled()) {
                return $redis_manager->get($key);
            }
        }
        return get_transient($this->cache_group . '_' . $key);
    }

    private function set_cache($key, $value, $expiration = null)
    {
        // Sanitize cache key to prevent injection
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        if ($expiration === null) {
            $expiration = $this->cache_expiration;
        }

        // Try Redis first, fallback to transients
        if (class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
            $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
            if ($redis_manager->is_enabled()) {
                return $redis_manager->set($key, $value, $expiration);
            }
        }
        return set_transient($this->cache_group . '_' . $key, $value, $expiration);
    }

    private function delete_cache($key)
    {
        // Sanitize cache key to prevent injection
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);

        // Try Redis first, fallback to transients
        if (class_exists('Boat_Chatbot_Redis_Cache_Manager')) {
            $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
            if ($redis_manager->is_enabled()) {
                return $redis_manager->delete($key);
            }
        }
        return delete_transient($this->cache_group . '_' . $key);
    }

    // Method to clear all caches (useful for admin)
    public function clear_cache()
    {
        global $wpdb;
        // Use prepared statement for cache clearing
        // Note: WordPress $wpdb->prepare handles LIKE patterns safely
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 OR option_name LIKE %s",
                '_transient_boat_chatbot_db_%',
                '_transient_timeout_boat_chatbot_db_%'
            )
        );
    }
}
?>
