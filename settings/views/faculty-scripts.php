    <!-- Profile Config for JS -->
    <script nonce="<?php echo $csp_nonce; ?>">
        window.USER_PROFILE_LOCKS = {
            dob: <?php echo $dob_locked ? 'true' : 'false'; ?>,
            gender: <?php echo $gender_locked ? 'true' : 'false'; ?>,
            nationality: <?php echo $nationality_locked ? 'true' : 'false'; ?>
        };
    </script>

