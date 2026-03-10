#!/usr/bin/env php
<?php
/**
 * Automated Layout Fix Script
 * Fixes sidebar integration and responsive layout issues
 */

echo "\n🔧 POP!Work Layout Fix Tool\n";
echo "============================\n\n";

$files_to_fix = [
    'job_completion_payment.php',
    'employer_attendance.php'
];

$fixes_applied = 0;
$errors = [];

foreach ($files_to_fix as $filename) {
    echo "Processing: $filename\n";
    
    if (!file_exists($filename)) {
        $errors[] = "❌ File not found: $filename";
        continue;
    }
    
    // Read file content
    $content = file_get_contents($filename);
    $original_content = $content;
    
    // Backup original file
    $backup_file = $filename . '.backup_' . date('Y-m-d_H-i-s');
    file_put_contents($backup_file, $content);
    echo "  ✅ Backup created: $backup_file\n";
    
    // Fix 1: Update body structure and sidebar include
    $old_body = '<body class="has-sidebar">
  
  <?php include \'includes/sidebar.php\'; ?>

  <div class="main-content">';
    
    $new_body = '<body>
  
  <div class="dashboard-wrapper">
    <?php include \'sidebar.php\'; ?>

    <div class="main-content">';
    
    if (strpos($content, $old_body) !== false) {
        $content = str_replace($old_body, $new_body, $content);
        echo "  ✅ Fixed: Body structure and sidebar include\n";
        $fixes_applied++;
    } else {
        echo "  ⚠️  Warning: Body structure not found or already fixed\n";
    }
    
    // Fix 2: Add responsive CSS before </style>
    $responsive_css = '
/* Responsive Layout Fixes */
.dashboard-wrapper {
  display: flex;
  min-height: 100vh;
  width: 100%;
}

.main-content {
  flex: 1;
  margin-left: 280px;
  transition: margin-left 0.3s ease;
  width: 100%;
  overflow-x: hidden;
}

@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
  }
}

@media (max-width: 992px) {
  .main-content {
    margin-left: 0;
  }
  
  .content-wrapper {
    padding: 20px !important;
  }
  
  .dashboard-header {
    padding: 20px !important;
  }
  
  .stats-section {
    padding: 20px !important;
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr !important;
  }
  
  .filter-controls {
    grid-template-columns: 1fr !important;
  }
  
  .content-wrapper {
    padding: 16px !important;
  }
  
  .dashboard-header {
    padding: 16px !important;
  }
  
  .header-title h1 {
    font-size: 24px !important;
  }
}

@media (max-width: 480px) {
  .stat-card {
    padding: 16px !important;
  }
  
  .stat-value {
    font-size: 20px !important;
  }
}
  </style>';
    
    if (strpos($content, '.dashboard-wrapper {') === false && strpos($content, '</style>') !== false) {
        $content = str_replace('  </style>', $responsive_css, $content);
        echo "  ✅ Added: Responsive CSS\n";
        $fixes_applied++;
    } else {
        echo "  ⚠️  Warning: Responsive CSS already exists or </style> tag not found\n";
    }
    
    // Fix 3: Add overflow-x: hidden to body
    $old_body_css = 'body {
      font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif;
      background: #f5f7fa;
      margin: 0;
      color: var(--text-dark);
    }';
    
    $new_body_css = 'body {
      font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif;
      background: #f5f7fa;
      margin: 0;
      color: var(--text-dark);
      overflow-x: hidden;
    }';
    
    if (strpos($content, 'overflow-x: hidden;') === false) {
        $content = str_replace($old_body_css, $new_body_css, $content);
        echo "  ✅ Fixed: Added overflow-x: hidden to body\n";
        $fixes_applied++;
    } else {
        echo "  ⚠️  Warning: overflow-x already set\n";
    }
    
    // Fix 4: Close wrapper properly at end of file
    $old_ending = '  </div>
</body>
</html>';
    
    $new_ending = '    </div>
  </div>
</body>
</html>';
    
    if (strpos($content, $old_ending) !== false) {
        $content = str_replace($old_ending, $new_ending, $content);
        echo "  ✅ Fixed: Wrapper closing tags\n";
        $fixes_applied++;
    }
    
    // Save fixed content
    if ($content !== $original_content) {
        file_put_contents($filename, $content);
        echo "  ✅ File updated successfully\n";
    } else {
        echo "  ℹ️  No changes needed\n";
    }
    
    echo "\n";
}

// Summary
echo "============================\n";
echo "Summary:\n";
echo "  Total fixes applied: $fixes_applied\n";

if (count($errors) > 0) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  $error\n";
    }
}

echo "\n✨ Layout fix complete!\n";
echo "\nNext steps:\n";
echo "1. Test the pages in your browser\n";
echo "2. Check responsiveness (resize window)\n";
echo "3. Verify sidebar appears correctly\n";
echo "4. If issues persist, check the backup files\n\n";
?>