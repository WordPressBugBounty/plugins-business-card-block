<?php
namespace BCB\Card;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dependency-free QR Code encoder (ISO/IEC 18004).
 *
 * Implements the full encoding pipeline: byte-mode segmentation, version
 * selection, Reed-Solomon error correction over GF(256), block interleaving,
 * function-pattern placement, the zigzag data walk, mask evaluation with the
 * four standard penalty rules, and BCH-protected format/version information.
 *
 * The class is self-contained: it requires no other file and no third-party
 * library. The only WordPress functions used are the escaping helpers and
 * wp_unique_id(), and only inside the SVG renderer.
 */
class QR {

    /**
     * Number of error correction codewords per block, indexed by
     * [ecc level 0-3][version 0-40]. Index 0 of the version axis is unused.
     *
     * @var array
     */
    private static $ecc_codewords_per_block = array(
        // Level L.
        array( -1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
        // Level M.
        array( -1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28 ),
        // Level Q.
        array( -1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
        // Level H.
        array( -1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
    );

    /**
     * Number of error correction blocks, indexed by
     * [ecc level 0-3][version 0-40]. Index 0 of the version axis is unused.
     *
     * @var array
     */
    private static $num_ecc_blocks = array(
        // Level L.
        array( -1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25 ),
        // Level M.
        array( -1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49 ),
        // Level Q.
        array( -1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68 ),
        // Level H.
        array( -1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81 ),
    );

    /**
     * Two-bit format indicator for each error correction level, indexed 0-3.
     *
     * @var array
     */
    private static $ecc_format_bits = array( 1, 0, 3, 2 );

    /**
     * GF(256) antilog table (length 512, wrapped for cheap multiplication).
     *
     * @var array|null
     */
    private static $gf_exp = null;

    /**
     * GF(256) log table.
     *
     * @var array|null
     */
    private static $gf_log = null;

    /**
     * Conservative allowlist of CSS named colours accepted by the renderer.
     *
     * @var array
     */
    private static $named_colors = array(
        'black', 'white', 'red', 'green', 'blue', 'yellow', 'cyan', 'magenta',
        'gray', 'grey', 'silver', 'maroon', 'olive', 'lime', 'aqua', 'teal',
        'navy', 'fuchsia', 'purple', 'orange', 'brown', 'pink', 'gold', 'beige',
        'ivory', 'tan', 'indigo', 'violet', 'khaki', 'salmon', 'crimson',
        'turquoise', 'lavender', 'plum', 'orchid', 'coral', 'darkblue',
        'darkgreen', 'darkred', 'darkgray', 'darkgrey', 'lightblue',
        'lightgreen', 'lightgray', 'lightgrey', 'midnightblue', 'slategray',
        'slategrey', 'steelblue', 'whitesmoke', 'currentcolor',
    );

    /**
     * Encode $text as a QR code and return a standalone SVG string.
     *
     * @param string $text Payload to encode (UTF-8).
     * @param array  $args {
     *     Optional. Rendering options.
     *
     *     @type int    $size   Pixel size of the rendered SVG (width == height). Default 200.
     *     @type int    $margin Quiet-zone width in modules. Default 4.
     *     @type string $dark   Dark module colour (CSS colour). Default '#000000'.
     *     @type string $light  Light/background colour, or 'transparent'. Default '#ffffff'.
     *     @type string $ecc    Error correction: 'L', 'M', 'Q', 'H'. Default 'M'.
     *     @type string $title  Accessible <title> text. Default ''.
     * }
     * @return string|\WP_Error SVG markup, or WP_Error on failure.
     */
    public static function svg( $text, $args = array() ) {
        if ( ! is_array( $args ) ) {
            $args = array();
        }

        $defaults = array(
            'size'   => 200,
            'margin' => 4,
            'dark'   => '#000000',
            'light'  => '#ffffff',
            'ecc'    => 'M',
            'title'  => '',
        );

        foreach ( $defaults as $key => $value ) {
            if ( ! isset( $args[ $key ] ) ) {
                $args[ $key ] = $value;
            }
        }

        $modules = self::matrix( $text, $args['ecc'] );

        if ( $modules instanceof \WP_Error ) {
            return $modules;
        }

        $size   = self::clamp_int( $args['size'], 32, 1024, 200 );
        $margin = self::clamp_int( $args['margin'], 0, 16, 4 );
        $dark   = self::sanitize_color( $args['dark'], '#000000' );
        $light  = self::sanitize_color( $args['light'], '#ffffff' );
        $title  = is_scalar( $args['title'] ) ? trim( (string) $args['title'] ) : '';

        $count    = count( $modules );
        $viewport = $count + ( 2 * $margin );

        $path = self::build_path( $modules, $margin );

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"'
            . ' width="' . esc_attr( $size ) . '"'
            . ' height="' . esc_attr( $size ) . '"'
            . ' viewBox="0 0 ' . esc_attr( $viewport ) . ' ' . esc_attr( $viewport ) . '"'
            . ' shape-rendering="crispEdges"'
            . ' focusable="false"';

        if ( '' !== $title ) {
            $title_id = wp_unique_id( 'bcb-qr-title-' );
            $svg     .= ' role="img" aria-labelledby="' . esc_attr( $title_id ) . '">';
            $svg     .= '<title id="' . esc_attr( $title_id ) . '">' . esc_html( $title ) . '</title>';
        } else {
            $svg .= ' role="presentation" aria-hidden="true">';
        }

        if ( 'transparent' !== $light ) {
            $svg .= '<rect width="' . esc_attr( $viewport ) . '" height="' . esc_attr( $viewport ) . '" fill="' . esc_attr( $light ) . '"/>';
        }

        $svg .= '<path fill="' . esc_attr( $dark ) . '" d="' . esc_attr( $path ) . '"/>';
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Encode $text and return the raw module matrix.
     *
     * @param string $text Payload to encode (UTF-8).
     * @param string $ecc  Error correction: 'L', 'M', 'Q', 'H'. Default 'M'.
     * @return array|\WP_Error Array of rows, each an array of 0/1 ints (no quiet zone).
     */
    public static function matrix( $text, $ecc = 'M' ) {
        $text = is_scalar( $text ) ? (string) $text : '';

        if ( '' === $text ) {
            return new \WP_Error( 'bcb_qr_empty', __( 'Nothing to encode: the QR code payload is empty.', 'business-card-block' ) );
        }

        $level = self::ecc_level( $ecc );

        // Byte mode: encode the raw UTF-8 bytes.
        $bytes  = array();
        $length = strlen( $text );

        for ( $i = 0; $i < $length; $i++ ) {
            $bytes[] = ord( $text[ $i ] );
        }

        $version = self::pick_version( $length, $level );

        if ( false === $version ) {
            return new \WP_Error(
                'bcb_qr_too_long',
                __( 'The QR code payload is too large to encode: it does not fit in a version 40 symbol at the requested error correction level.', 'business-card-block' )
            );
        }

        $data_codewords = self::build_codewords( $bytes, $version, $level );
        $all_codewords  = self::add_ecc_and_interleave( $data_codewords, $version, $level );

        $size    = ( 4 * $version ) + 17;
        $modules = array();
        $funcs   = array();

        for ( $y = 0; $y < $size; $y++ ) {
            $modules[ $y ] = array_fill( 0, $size, 0 );
            $funcs[ $y ]   = array_fill( 0, $size, false );
        }

        self::draw_function_patterns( $modules, $funcs, $version, $level );
        self::draw_codewords( $modules, $funcs, $all_codewords );

        // Evaluate all eight masks and keep the lowest-penalty one.
        $best_mask  = 0;
        $best_score = -1;

        for ( $mask = 0; $mask < 8; $mask++ ) {
            self::draw_format_bits( $modules, $funcs, $level, $mask );
            self::apply_mask( $modules, $funcs, $mask );

            $score = self::penalty_score( $modules );

            if ( $best_score < 0 || $score < $best_score ) {
                $best_score = $score;
                $best_mask  = $mask;
            }

            // XOR masking is an involution, so re-applying restores the matrix.
            self::apply_mask( $modules, $funcs, $mask );
        }

        self::draw_format_bits( $modules, $funcs, $level, $best_mask );
        self::apply_mask( $modules, $funcs, $best_mask );

        return $modules;
    }

    /* ---------------------------------------------------------------------
     * Encoding pipeline.
     * ------------------------------------------------------------------ */

    /**
     * Normalise an error correction level string to its table index.
     *
     * @param string $ecc Level string.
     * @return int 0 (L), 1 (M), 2 (Q) or 3 (H).
     */
    private static function ecc_level( $ecc ) {
        $map = array( 'L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3 );
        $key = is_scalar( $ecc ) ? strtoupper( trim( (string) $ecc ) ) : '';

        return isset( $map[ $key ] ) ? $map[ $key ] : 1;
    }

    /**
     * Total number of raw data modules available in a version, before the
     * codeword split (i.e. including the trailing remainder bits).
     *
     * @param int $version Version 1-40.
     * @return int Module count.
     */
    private static function raw_data_modules( $version ) {
        $result = ( ( 16 * $version ) + 128 ) * $version + 64;

        if ( $version >= 2 ) {
            $num_align = intdiv( $version, 7 ) + 2;
            $result   -= ( ( 25 * $num_align ) - 10 ) * $num_align - 55;

            if ( $version >= 7 ) {
                $result -= 36;
            }
        }

        return $result;
    }

    /**
     * Total number of codewords (data + error correction) for a version.
     *
     * @param int $version Version 1-40.
     * @return int Codeword count.
     */
    private static function raw_codewords( $version ) {
        return intdiv( self::raw_data_modules( $version ), 8 );
    }

    /**
     * Number of usable data codewords for a version and ECC level.
     *
     * @param int $version Version 1-40.
     * @param int $level   ECC level index 0-3.
     * @return int Data codeword count.
     */
    private static function data_codewords( $version, $level ) {
        $blocks = self::$num_ecc_blocks[ $level ][ $version ];
        $ecc_cw = self::$ecc_codewords_per_block[ $level ][ $version ];

        return self::raw_codewords( $version ) - ( $blocks * $ecc_cw );
    }

    /**
     * Pick the smallest version that fits a byte-mode payload.
     *
     * @param int $length Payload length in bytes.
     * @param int $level  ECC level index 0-3.
     * @return int|false Version 1-40, or false when the payload never fits.
     */
    private static function pick_version( $length, $level ) {
        for ( $version = 1; $version <= 40; $version++ ) {
            $cc_bits = ( $version <= 9 ) ? 8 : 16;

            // The character count indicator must be able to hold the length.
            if ( $length >= ( 1 << $cc_bits ) ) {
                continue;
            }

            $needed    = 4 + $cc_bits + ( 8 * $length );
            $available = self::data_codewords( $version, $level ) * 8;

            if ( $needed <= $available ) {
                return $version;
            }
        }

        return false;
    }

    /**
     * Build the padded data codeword stream for a payload.
     *
     * @param array $bytes   Payload bytes.
     * @param int   $version Version 1-40.
     * @param int   $level   ECC level index 0-3.
     * @return array Data codewords (ints 0-255).
     */
    private static function build_codewords( $bytes, $version, $level ) {
        $capacity_bits = self::data_codewords( $version, $level ) * 8;
        $bits          = array();

        // Mode indicator: byte mode.
        self::append_bits( $bits, 4, 4 );

        // Character count indicator.
        self::append_bits( $bits, count( $bytes ), ( $version <= 9 ) ? 8 : 16 );

        foreach ( $bytes as $byte ) {
            self::append_bits( $bits, $byte, 8 );
        }

        // Terminator: up to four zero bits.
        $terminator = $capacity_bits - count( $bits );
        if ( $terminator > 4 ) {
            $terminator = 4;
        }
        self::append_bits( $bits, 0, $terminator );

        // Pad to a byte boundary.
        self::append_bits( $bits, 0, ( 8 - ( count( $bits ) % 8 ) ) % 8 );

        // Alternating pad bytes.
        $pad = 0xEC;
        while ( count( $bits ) < $capacity_bits ) {
            self::append_bits( $bits, $pad, 8 );
            $pad = ( 0xEC === $pad ) ? 0x11 : 0xEC;
        }

        $codewords = array();
        $total     = count( $bits );

        for ( $i = 0; $i < $total; $i += 8 ) {
            $byte = 0;
            for ( $b = 0; $b < 8; $b++ ) {
                $byte = ( $byte << 1 ) | $bits[ $i + $b ];
            }
            $codewords[] = $byte;
        }

        return $codewords;
    }

    /**
     * Append the low $length bits of $value to a bit array, MSB first.
     *
     * @param array $bits   Bit array, passed by reference.
     * @param int   $value  Value to append.
     * @param int   $length Number of bits.
     * @return void
     */
    private static function append_bits( &$bits, $value, $length ) {
        for ( $i = $length - 1; $i >= 0; $i-- ) {
            $bits[] = ( $value >> $i ) & 1;
        }
    }

    /**
     * Split data codewords into blocks, append Reed-Solomon codewords and
     * interleave everything into the final codeword sequence.
     *
     * @param array $data    Data codewords.
     * @param int   $version Version 1-40.
     * @param int   $level   ECC level index 0-3.
     * @return array Interleaved codewords.
     */
    private static function add_ecc_and_interleave( $data, $version, $level ) {
        $num_blocks    = self::$num_ecc_blocks[ $level ][ $version ];
        $block_ecc_len = self::$ecc_codewords_per_block[ $level ][ $version ];
        $raw_cw        = self::raw_codewords( $version );

        $short_blocks    = $num_blocks - ( $raw_cw % $num_blocks );
        $short_block_len = intdiv( $raw_cw, $num_blocks );
        $short_data_len  = $short_block_len - $block_ecc_len;

        $generator   = self::rs_generator( $block_ecc_len );
        $data_blocks = array();
        $ecc_blocks  = array();
        $offset      = 0;

        for ( $i = 0; $i < $num_blocks; $i++ ) {
            $len   = $short_data_len + ( ( $i < $short_blocks ) ? 0 : 1 );
            $block = array_slice( $data, $offset, $len );
            $offset += $len;

            $data_blocks[] = $block;
            $ecc_blocks[]  = self::rs_remainder( $block, $generator, $block_ecc_len );
        }

        $result   = array();
        $max_data = $short_data_len + 1;

        for ( $i = 0; $i < $max_data; $i++ ) {
            for ( $j = 0; $j < $num_blocks; $j++ ) {
                if ( isset( $data_blocks[ $j ][ $i ] ) ) {
                    $result[] = $data_blocks[ $j ][ $i ];
                }
            }
        }

        for ( $i = 0; $i < $block_ecc_len; $i++ ) {
            for ( $j = 0; $j < $num_blocks; $j++ ) {
                $result[] = $ecc_blocks[ $j ][ $i ];
            }
        }

        return $result;
    }

    /* ---------------------------------------------------------------------
     * Reed-Solomon over GF(256), primitive polynomial 0x11D.
     * ------------------------------------------------------------------ */

    /**
     * Lazily build the GF(256) log and antilog tables.
     *
     * @return void
     */
    private static function init_gf() {
        if ( null !== self::$gf_exp ) {
            return;
        }

        $exp = array_fill( 0, 512, 0 );
        $log = array_fill( 0, 256, 0 );
        $x   = 1;

        for ( $i = 0; $i < 255; $i++ ) {
            $exp[ $i ] = $x;
            $log[ $x ] = $i;

            $x <<= 1;
            if ( $x & 0x100 ) {
                $x ^= 0x11D;
            }
        }

        for ( $i = 255; $i < 512; $i++ ) {
            $exp[ $i ] = $exp[ $i - 255 ];
        }

        self::$gf_exp = $exp;
        self::$gf_log = $log;
    }

    /**
     * Multiply two GF(256) field elements.
     *
     * @param int $a First operand.
     * @param int $b Second operand.
     * @return int Product.
     */
    private static function gf_mul( $a, $b ) {
        if ( 0 === $a || 0 === $b ) {
            return 0;
        }

        return self::$gf_exp[ self::$gf_log[ $a ] + self::$gf_log[ $b ] ];
    }

    /**
     * Build the Reed-Solomon generator polynomial of the given degree.
     *
     * @param int $degree Number of error correction codewords.
     * @return array Coefficients of degree..0, without the leading 1.
     */
    private static function rs_generator( $degree ) {
        self::init_gf();

        $poly = array( 1 );

        for ( $i = 0; $i < $degree; $i++ ) {
            $root = self::$gf_exp[ $i ];
            $next = array_fill( 0, count( $poly ) + 1, 0 );

            for ( $j = 0; $j < count( $poly ); $j++ ) {
                $next[ $j ]     ^= $poly[ $j ];
                $next[ $j + 1 ] ^= self::gf_mul( $poly[ $j ], $root );
            }

            $poly = $next;
        }

        // Drop the (always 1) leading coefficient; only the rest is needed.
        return array_slice( $poly, 1 );
    }

    /**
     * Compute the Reed-Solomon remainder (the EC codewords) for one block.
     *
     * @param array $data      Block data codewords.
     * @param array $generator Generator polynomial without its leading 1.
     * @param int   $ecc_len   Number of EC codewords.
     * @return array EC codewords.
     */
    private static function rs_remainder( $data, $generator, $ecc_len ) {
        $result = array_fill( 0, $ecc_len, 0 );

        foreach ( $data as $byte ) {
            $factor = $byte ^ $result[0];

            array_shift( $result );
            $result[] = 0;

            for ( $i = 0; $i < $ecc_len; $i++ ) {
                $result[ $i ] ^= self::gf_mul( $generator[ $i ], $factor );
            }
        }

        return $result;
    }

    /* ---------------------------------------------------------------------
     * Module placement.
     * ------------------------------------------------------------------ */

    /**
     * Draw every function pattern into the matrix.
     *
     * @param array $modules Module matrix, by reference.
     * @param array $funcs   Function-module flags, by reference.
     * @param int   $version Version 1-40.
     * @param int   $level   ECC level index 0-3.
     * @return void
     */
    private static function draw_function_patterns( &$modules, &$funcs, $version, $level ) {
        $size = count( $modules );

        // Timing patterns.
        for ( $i = 0; $i < $size; $i++ ) {
            $dark = ( 0 === $i % 2 ) ? 1 : 0;
            self::set_function_module( $modules, $funcs, 6, $i, $dark );
            self::set_function_module( $modules, $funcs, $i, 6, $dark );
        }

        // Finder patterns with their separators (overwrite some timing modules).
        self::draw_finder( $modules, $funcs, 3, 3 );
        self::draw_finder( $modules, $funcs, 3, $size - 4 );
        self::draw_finder( $modules, $funcs, $size - 4, 3 );

        // Alignment patterns.
        $positions = self::alignment_positions( $version );
        $count     = count( $positions );

        for ( $i = 0; $i < $count; $i++ ) {
            for ( $j = 0; $j < $count; $j++ ) {
                $corner = ( 0 === $i && 0 === $j )
                    || ( 0 === $i && ( $count - 1 ) === $j )
                    || ( ( $count - 1 ) === $i && 0 === $j );

                if ( ! $corner ) {
                    self::draw_alignment( $modules, $funcs, $positions[ $i ], $positions[ $j ] );
                }
            }
        }

        // Reserve the format areas (a dummy mask; overwritten during masking).
        self::draw_format_bits( $modules, $funcs, $level, 0 );

        // Version information.
        self::draw_version( $modules, $funcs, $version );
    }

    /**
     * Set a single module and flag it as a function module.
     *
     * @param array $modules Module matrix, by reference.
     * @param array $funcs   Function-module flags, by reference.
     * @param int   $row     Row index.
     * @param int   $col     Column index.
     * @param int   $value   0 or 1.
     * @return void
     */
    private static function set_function_module( &$modules, &$funcs, $row, $col, $value ) {
        $modules[ $row ][ $col ] = $value ? 1 : 0;
        $funcs[ $row ][ $col ]   = true;
    }

    /**
     * Draw one 7x7 finder pattern plus its separator ring.
     *
     * @param array $modules Module matrix, by reference.
     * @param array $funcs   Function-module flags, by reference.
     * @param int   $row     Centre row.
     * @param int   $col     Centre column.
     * @return void
     */
    private static function draw_finder( &$modules, &$funcs, $row, $col ) {
        $size = count( $modules );

        for ( $dy = -4; $dy <= 4; $dy++ ) {
            for ( $dx = -4; $dx <= 4; $dx++ ) {
                $dist = max( abs( $dx ), abs( $dy ) );
                $y    = $row + $dy;
                $x    = $col + $dx;

                if ( $y >= 0 && $y < $size && $x >= 0 && $x < $size ) {
                    self::set_function_module( $modules, $funcs, $y, $x, ( 2 !== $dist && 4 !== $dist ) ? 1 : 0 );
                }
            }
        }
    }

    /**
     * Draw one 5x5 alignment pattern.
     *
     * @param array $modules Module matrix, by reference.
     * @param array $funcs   Function-module flags, by reference.
     * @param int   $row     Centre row.
     * @param int   $col     Centre column.
     * @return void
     */
    private static function draw_alignment( &$modules, &$funcs, $row, $col ) {
        for ( $dy = -2; $dy <= 2; $dy++ ) {
            for ( $dx = -2; $dx <= 2; $dx++ ) {
                $dark = ( 1 !== max( abs( $dx ), abs( $dy ) ) ) ? 1 : 0;
                self::set_function_module( $modules, $funcs, $row + $dy, $col + $dx, $dark );
            }
        }
    }

    /**
     * Alignment pattern centre coordinates for a version.
     *
     * @param int $version Version 1-40.
     * @return array Ascending list of coordinates (empty for version 1).
     */
    private static function alignment_positions( $version ) {
        if ( 1 === $version ) {
            return array();
        }

        $num_align = intdiv( $version, 7 ) + 2;

        if ( 32 === $version ) {
            $step = 26;
        } else {
            $step = intdiv( ( $version * 4 ) + ( $num_align * 2 ) + 1, ( $num_align * 2 ) - 2 ) * 2;
        }

        $result = array( 6 );
        $pos    = ( $version * 4 ) + 10;

        while ( count( $result ) < $num_align ) {
            array_splice( $result, 1, 0, array( $pos ) );
            $pos -= $step;
        }

        return $result;
    }

    /**
     * Draw the 15-bit BCH-protected format information in both format areas.
     *
     * @param array $modules Module matrix, by reference.
     * @param array $funcs   Function-module flags, by reference.
     * @param int   $level   ECC level index 0-3.
     * @param int   $mask    Mask pattern 0-7.
     * @return void
     */
    private static function draw_format_bits( &$modules, &$funcs, $level, $mask ) {
        $size = count( $modules );
        $data = ( self::$ecc_format_bits[ $level ] << 3 ) | $mask;
        $rem  = $data;

        for ( $i = 0; $i < 10; $i++ ) {
            $rem = ( $rem << 1 ) ^ ( ( ( $rem >> 9 ) & 1 ) * 0x537 );
        }

        $bits = ( ( ( $data << 10 ) | $rem ) ^ 0x5412 ) & 0x7FFF;

        // First copy, around the top-left finder.
        for ( $i = 0; $i <= 5; $i++ ) {
            self::set_function_module( $modules, $funcs, $i, 8, ( $bits >> $i ) & 1 );
        }

        self::set_function_module( $modules, $funcs, 7, 8, ( $bits >> 6 ) & 1 );
        self::set_function_module( $modules, $funcs, 8, 8, ( $bits >> 7 ) & 1 );
        self::set_function_module( $modules, $funcs, 8, 7, ( $bits >> 8 ) & 1 );

        for ( $i = 9; $i < 15; $i++ ) {
            self::set_function_module( $modules, $funcs, 8, 14 - $i, ( $bits >> $i ) & 1 );
        }

        // Second copy, split between the top-right and bottom-left finders.
        for ( $i = 0; $i < 8; $i++ ) {
            self::set_function_module( $modules, $funcs, 8, $size - 1 - $i, ( $bits >> $i ) & 1 );
        }

        for ( $i = 8; $i < 15; $i++ ) {
            self::set_function_module( $modules, $funcs, $size - 15 + $i, 8, ( $bits >> $i ) & 1 );
        }

        // The dark module.
        self::set_function_module( $modules, $funcs, $size - 8, 8, 1 );
    }

    /**
     * Draw the 18-bit BCH-protected version information (versions 7 and up).
     *
     * @param array $modules Module matrix, by reference.
     * @param array $funcs   Function-module flags, by reference.
     * @param int   $version Version 1-40.
     * @return void
     */
    private static function draw_version( &$modules, &$funcs, $version ) {
        if ( $version < 7 ) {
            return;
        }

        $size = count( $modules );
        $rem  = $version;

        for ( $i = 0; $i < 12; $i++ ) {
            $rem = ( $rem << 1 ) ^ ( ( ( $rem >> 11 ) & 1 ) * 0x1F25 );
        }

        $bits = ( ( $version << 12 ) | $rem ) & 0x3FFFF;

        for ( $i = 0; $i < 18; $i++ ) {
            $bit = ( $bits >> $i ) & 1;
            $a   = $size - 11 + ( $i % 3 );
            $b   = intdiv( $i, 3 );

            self::set_function_module( $modules, $funcs, $b, $a, $bit );
            self::set_function_module( $modules, $funcs, $a, $b, $bit );
        }
    }

    /**
     * Place the interleaved codewords using the standard zigzag walk.
     *
     * @param array $modules   Module matrix, by reference.
     * @param array $funcs     Function-module flags, by reference.
     * @param array $codewords Interleaved codewords.
     * @return void
     */
    private static function draw_codewords( &$modules, &$funcs, $codewords ) {
        $size      = count( $modules );
        $total     = count( $codewords ) * 8;
        $bit_index = 0;

        for ( $right = $size - 1; $right >= 1; $right -= 2 ) {
            if ( 6 === $right ) {
                $right = 5;
            }

            $upward = ( 0 === ( ( $right + 1 ) & 2 ) );

            for ( $vert = 0; $vert < $size; $vert++ ) {
                for ( $j = 0; $j < 2; $j++ ) {
                    $col = $right - $j;
                    $row = $upward ? ( $size - 1 - $vert ) : $vert;

                    if ( ! $funcs[ $row ][ $col ] && $bit_index < $total ) {
                        $byte = $codewords[ $bit_index >> 3 ];

                        $modules[ $row ][ $col ] = ( $byte >> ( 7 - ( $bit_index & 7 ) ) ) & 1;
                        $bit_index++;
                    }
                    // Remainder bits stay zero, which is what the standard wants.
                }
            }
        }
    }

    /**
     * XOR the given mask over every non-function module. Self-inverse.
     *
     * @param array $modules Module matrix, by reference.
     * @param array $funcs   Function-module flags, by reference.
     * @param int   $mask    Mask pattern 0-7.
     * @return void
     */
    private static function apply_mask( &$modules, &$funcs, $mask ) {
        $size = count( $modules );

        for ( $row = 0; $row < $size; $row++ ) {
            for ( $col = 0; $col < $size; $col++ ) {
                if ( $funcs[ $row ][ $col ] ) {
                    continue;
                }

                switch ( $mask ) {
                    case 0:
                        $invert = ( 0 === ( ( $row + $col ) % 2 ) );
                        break;
                    case 1:
                        $invert = ( 0 === ( $row % 2 ) );
                        break;
                    case 2:
                        $invert = ( 0 === ( $col % 3 ) );
                        break;
                    case 3:
                        $invert = ( 0 === ( ( $row + $col ) % 3 ) );
                        break;
                    case 4:
                        $invert = ( 0 === ( ( intdiv( $row, 2 ) + intdiv( $col, 3 ) ) % 2 ) );
                        break;
                    case 5:
                        $invert = ( 0 === ( ( ( $row * $col ) % 2 ) + ( ( $row * $col ) % 3 ) ) );
                        break;
                    case 6:
                        $invert = ( 0 === ( ( ( ( $row * $col ) % 2 ) + ( ( $row * $col ) % 3 ) ) % 2 ) );
                        break;
                    default:
                        $invert = ( 0 === ( ( ( ( $row + $col ) % 2 ) + ( ( $row * $col ) % 3 ) ) % 2 ) );
                        break;
                }

                if ( $invert ) {
                    $modules[ $row ][ $col ] ^= 1;
                }
            }
        }
    }

    /* ---------------------------------------------------------------------
     * Mask scoring.
     * ------------------------------------------------------------------ */

    /**
     * Score a masked matrix with the four standard penalty rules.
     *
     * @param array $modules Module matrix.
     * @return int Penalty score (lower is better).
     */
    private static function penalty_score( $modules ) {
        $size    = count( $modules );
        $penalty = 0;
        $dark    = 0;

        // Rule 1 (rows) and rule 2, plus the dark module tally.
        for ( $row = 0; $row < $size; $row++ ) {
            $run_colour = $modules[ $row ][0];
            $run_length = 1;

            for ( $col = 0; $col < $size; $col++ ) {
                $value = $modules[ $row ][ $col ];
                $dark += $value;

                if ( $col > 0 ) {
                    if ( $value === $run_colour ) {
                        $run_length++;
                    } else {
                        if ( $run_length >= 5 ) {
                            $penalty += 3 + ( $run_length - 5 );
                        }
                        $run_colour = $value;
                        $run_length = 1;
                    }
                }

                // Rule 2: 2x2 blocks of one colour.
                if ( $row > 0 && $col > 0
                    && $value === $modules[ $row ][ $col - 1 ]
                    && $value === $modules[ $row - 1 ][ $col ]
                    && $value === $modules[ $row - 1 ][ $col - 1 ]
                ) {
                    $penalty += 3;
                }
            }

            if ( $run_length >= 5 ) {
                $penalty += 3 + ( $run_length - 5 );
            }
        }

        // Rule 1 (columns).
        for ( $col = 0; $col < $size; $col++ ) {
            $run_colour = $modules[0][ $col ];
            $run_length = 1;

            for ( $row = 1; $row < $size; $row++ ) {
                $value = $modules[ $row ][ $col ];

                if ( $value === $run_colour ) {
                    $run_length++;
                } else {
                    if ( $run_length >= 5 ) {
                        $penalty += 3 + ( $run_length - 5 );
                    }
                    $run_colour = $value;
                    $run_length = 1;
                }
            }

            if ( $run_length >= 5 ) {
                $penalty += 3 + ( $run_length - 5 );
            }
        }

        // Rule 3: finder-like 1:1:3:1:1 patterns with a four-module light run.
        for ( $row = 0; $row < $size; $row++ ) {
            $window = 0;

            for ( $col = 0; $col < $size; $col++ ) {
                $window = ( ( $window << 1 ) | $modules[ $row ][ $col ] ) & 0x7FF;

                if ( $col >= 10 && ( 0x5D0 === $window || 0x05D === $window ) ) {
                    $penalty += 40;
                }
            }
        }

        for ( $col = 0; $col < $size; $col++ ) {
            $window = 0;

            for ( $row = 0; $row < $size; $row++ ) {
                $window = ( ( $window << 1 ) | $modules[ $row ][ $col ] ) & 0x7FF;

                if ( $row >= 10 && ( 0x5D0 === $window || 0x05D === $window ) ) {
                    $penalty += 40;
                }
            }
        }

        // Rule 4: deviation of the dark module ratio from 50%.
        $total    = $size * $size;
        $percent  = ( $dark * 100 ) / $total;
        $penalty += (int) floor( abs( $percent - 50 ) / 5 ) * 10;

        return $penalty;
    }

    /* ---------------------------------------------------------------------
     * Rendering helpers.
     * ------------------------------------------------------------------ */

    /**
     * Build the "d" attribute covering every dark module.
     *
     * Dark modules are greedily merged into maximal rectangles (widest first,
     * then as tall as that width allows) so that the whole symbol fits in a
     * single path with roughly a third of the subpaths a per-module encoding
     * would need. Rectangles never overlap, so the default fill rule applies.
     *
     * @param array $modules Module matrix.
     * @param int   $margin  Quiet-zone width in modules.
     * @return string Path data.
     */
    private static function build_path( $modules, $margin ) {
        $count = count( $modules );
        $used  = array();
        $path  = '';

        for ( $y = 0; $y < $count; $y++ ) {
            $used[ $y ] = array_fill( 0, $count, false );
        }

        for ( $y = 0; $y < $count; $y++ ) {
            for ( $x = 0; $x < $count; $x++ ) {
                if ( 1 !== $modules[ $y ][ $x ] || $used[ $y ][ $x ] ) {
                    continue;
                }

                // Widest run starting here.
                $width = 1;
                while ( ( $x + $width ) < $count
                    && 1 === $modules[ $y ][ $x + $width ]
                    && ! $used[ $y ][ $x + $width ]
                ) {
                    $width++;
                }

                // Grow downwards while every module of the slice is free.
                $height = 1;
                while ( ( $y + $height ) < $count ) {
                    $fits = true;

                    for ( $k = 0; $k < $width; $k++ ) {
                        if ( 1 !== $modules[ $y + $height ][ $x + $k ] || $used[ $y + $height ][ $x + $k ] ) {
                            $fits = false;
                            break;
                        }
                    }

                    if ( ! $fits ) {
                        break;
                    }

                    $height++;
                }

                for ( $dy = 0; $dy < $height; $dy++ ) {
                    for ( $dx = 0; $dx < $width; $dx++ ) {
                        $used[ $y + $dy ][ $x + $dx ] = true;
                    }
                }

                $path .= 'M' . ( $x + $margin ) . ' ' . ( $y + $margin )
                    . 'h' . $width . 'v' . $height . 'h-' . $width . 'z';
            }
        }

        return $path;
    }

    /**
     * Clamp a value into an integer range, falling back when not numeric.
     *
     * @param mixed $value    Raw value.
     * @param int   $min      Minimum.
     * @param int   $max      Maximum.
     * @param int   $fallback Value used when $value is not numeric.
     * @return int Clamped integer.
     */
    private static function clamp_int( $value, $min, $max, $fallback ) {
        if ( ! is_numeric( $value ) ) {
            return $fallback;
        }

        $value = (int) $value;

        if ( $value < $min ) {
            return $min;
        }

        if ( $value > $max ) {
            return $max;
        }

        return $value;
    }

    /**
     * Validate a CSS colour, falling back to a safe default when unrecognised.
     *
     * Only #rgb, #rrggbb, #rrggbbaa, 'transparent' and an allowlist of named
     * colours are accepted, so nothing can be injected into the SVG markup.
     *
     * @param mixed  $color   Raw colour value.
     * @param string $default Fallback colour.
     * @return string Safe colour string.
     */
    private static function sanitize_color( $color, $default ) {
        if ( ! is_scalar( $color ) ) {
            return $default;
        }

        $color = trim( (string) $color );

        if ( '' === $color ) {
            return $default;
        }

        if ( preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color ) ) {
            return $color;
        }

        $lower = strtolower( $color );

        if ( 'transparent' === $lower || in_array( $lower, self::$named_colors, true ) ) {
            return $lower;
        }

        return $default;
    }
}
