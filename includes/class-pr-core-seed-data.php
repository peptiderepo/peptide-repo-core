<?php
declare(strict_types=1);

/**
 * Seed data fixture for development and testing.
 *
 * What: Creates 3 peptide posts, 10 dosing rows, and 5 legal cells.
 * Who calls it: WP-CLI command or admin action (manual trigger only).
 * Dependencies: PR_Core_Peptide_CPT, PR_Core_Dosing_Repository, PR_Core_Legal_Repository.
 *
 * @see ARCHITECTURE.md — Seed data specification.
 */
class PR_Core_Seed_Data {

	/**
	 * Run the seed to populate the database with sample data.
	 *
	 * Side effects: creates posts, inserts dosing rows and legal cells.
	 *
	 * @return array{peptides: int, dosing_rows: int, legal_cells: int} Counts of created records.
	 */
	public static function run(): array {
		$peptide_ids = self::seed_peptides();
		$dosing      = self::seed_dosing_rows( $peptide_ids );
		$legal       = self::seed_legal_cells( $peptide_ids );

		return [
			'peptides'    => count( $peptide_ids ),
			'dosing_rows' => $dosing,
			'legal_cells' => $legal,
		];
	}

	/**
	 * Create 3 canonical peptide posts.
	 *
	 * @return array<string, int> Map of slug => post ID.
	 */
	private static function seed_peptides(): array {
		$peptides = [
			[
				'title'             => 'BPC-157',
				'slug'              => 'bpc-157',
				'excerpt'           => 'Body Protection Compound-157, a pentadecapeptide derived from human gastric juice.',
				'display_name'      => 'BPC-157',
				'aliases'           => '["Body Protection Compound-157","Bepecin","PL 14736","PL-10"]',
				'molecular_formula' => 'C62H98N16O22',
				'molecular_weight'  => 1419.53,
				'cas_number'        => '137525-51-0',
				'drugbank_id'       => '',
				'chembl_id'         => '',
				'evidence_strength' => 'observational',
			],
			[
				'title'             => 'Semaglutide',
				'slug'              => 'semaglutide',
				'excerpt'           => 'A GLP-1 receptor agonist approved for type 2 diabetes and chronic weight management.',
				'display_name'      => 'Semaglutide',
				'aliases'           => '["Ozempic","Wegovy","Rybelsus"]',
				'molecular_formula' => 'C187H291N45O59',
				'molecular_weight'  => 4113.58,
				'cas_number'        => '910463-68-2',
				'drugbank_id'       => 'DB13928',
				'chembl_id'         => 'CHEMBL3137309',
				'evidence_strength' => 'meta-analysis',
			],
			[
				'title'             => 'TB-500',
				'slug'              => 'tb-500',
				'excerpt'           => 'Thymosin Beta-4 fragment, a synthetic peptide used in tissue repair research.',
				'display_name'      => 'TB-500',
				'aliases'           => '["Thymosin Beta-4","Tβ4"]',
				'molecular_formula' => 'C212H350N56O78S',
				'molecular_weight'  => 4963.44,
				'cas_number'        => '77591-33-4',
				'drugbank_id'       => '',
				'chembl_id'         => '',
				'evidence_strength' => 'preclinical',
			],
		];

		$ids = [];
		foreach ( $peptides as $p ) {
			$existing = get_posts( [
				'post_type' => PR_Core_Peptide_CPT::POST_TYPE,
				'name'      => $p['slug'],
				'posts_per_page' => 1,
				'post_status' => 'any',
			] );

			if ( ! empty( $existing ) ) {
				$ids[ $p['slug'] ] = $existing[0]->ID;
				continue;
			}

			$post_id = wp_insert_post( [
				'post_type'    => PR_Core_Peptide_CPT::POST_TYPE,
				'post_title'   => $p['title'],
				'post_name'    => $p['slug'],
				'post_excerpt' => $p['excerpt'],
				'post_status'  => 'publish',
				'post_content' => '',
			] );

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			foreach ( [ 'display_name', 'aliases', 'molecular_formula', 'molecular_weight', 'cas_number', 'drugbank_id', 'chembl_id', 'evidence_strength' ] as $key ) {
				update_post_meta( $post_id, $key, $p[ $key ] );
			}
			update_post_meta( $post_id, 'editorial_review_status', 'published' );
			update_post_meta( $post_id, 'last_editorial_review_at', current_time( 'mysql' ) );

			$ids[ $p['slug'] ] = $post_id;
		}

		return $ids;
	}

	/**
	 * Seed 10 dosing rows across the 3 peptides.
	 *
	 * @param array<string, int> $peptide_ids Map of slug => post ID.
	 * @return int Number of rows inserted.
	 */
	private static function seed_dosing_rows( array $peptide_ids ): int {
		$repo = new PR_Core_Dosing_Repository();
		$rows = [
			// BPC-157 (4 rows).
			[ 'peptide' => 'bpc-157', 'dose_min' => 200, 'dose_max' => 300, 'dose_unit' => 'mcg', 'route' => 'subq', 'frequency' => 'twice daily', 'population' => 'healthy', 'evidence_strength' => 'observational', 'study_title' => 'BPC 157 in wound healing review', 'study_year' => 2021, 'citation_pubmed_id' => '34537800' ],
			[ 'peptide' => 'bpc-157', 'dose_min' => 500, 'dose_max' => 500, 'dose_unit' => 'mcg', 'route' => 'subq', 'frequency' => 'daily', 'population' => 'healthy', 'evidence_strength' => 'case-series', 'study_title' => 'Subcutaneous BPC-157 in tendon repair', 'study_year' => 2019, 'citation_pubmed_id' => '30915550' ],
			[ 'peptide' => 'bpc-157', 'dose_min' => 10, 'dose_max' => 10, 'dose_unit' => 'mcg', 'route' => 'oral', 'frequency' => 'daily', 'population' => 'animal', 'evidence_strength' => 'preclinical', 'study_title' => 'Oral BPC-157 in rat gastric ulcer model', 'study_year' => 2018, 'citation_pubmed_id' => '29869189' ],
			[ 'peptide' => 'bpc-157', 'dose_min' => 250, 'dose_max' => 750, 'dose_unit' => 'mcg', 'route' => 'im', 'frequency' => 'daily', 'population' => 'clinical', 'evidence_strength' => 'case-series', 'study_title' => 'Intramuscular BPC-157 for muscle injuries', 'study_year' => 2020, 'citation_pubmed_id' => '33312000' ],
			// Semaglutide (4 rows).
			[ 'peptide' => 'semaglutide', 'dose_min' => 0.25, 'dose_max' => 0.25, 'dose_unit' => 'mg', 'route' => 'subq', 'frequency' => 'weekly', 'population' => 'clinical', 'evidence_strength' => 'rct-large', 'study_title' => 'SUSTAIN 1: Semaglutide in T2DM', 'study_year' => 2017, 'citation_pubmed_id' => '28930514', 'indication' => 'Type 2 diabetes — initiation dose' ],
			[ 'peptide' => 'semaglutide', 'dose_min' => 1.0, 'dose_max' => 1.0, 'dose_unit' => 'mg', 'route' => 'subq', 'frequency' => 'weekly', 'population' => 'clinical', 'evidence_strength' => 'rct-large', 'study_title' => 'SUSTAIN 6: CV outcomes with semaglutide', 'study_year' => 2016, 'citation_pubmed_id' => '27633186', 'indication' => 'Type 2 diabetes — maintenance' ],
			[ 'peptide' => 'semaglutide', 'dose_min' => 2.4, 'dose_max' => 2.4, 'dose_unit' => 'mg', 'route' => 'subq', 'frequency' => 'weekly', 'population' => 'clinical', 'evidence_strength' => 'rct-large', 'study_title' => 'STEP 1: Semaglutide 2.4mg for obesity', 'study_year' => 2021, 'citation_pubmed_id' => '33567185', 'indication' => 'Chronic weight management' ],
			[ 'peptide' => 'semaglutide', 'dose_min' => 14, 'dose_max' => 14, 'dose_unit' => 'mg', 'route' => 'oral', 'frequency' => 'daily', 'population' => 'clinical', 'evidence_strength' => 'rct-large', 'study_title' => 'PIONEER 1: Oral semaglutide in T2DM', 'study_year' => 2019, 'citation_pubmed_id' => '30924169', 'indication' => 'Type 2 diabetes — oral formulation' ],
			// TB-500 (2 rows).
			[ 'peptide' => 'tb-500', 'dose_min' => 2, 'dose_max' => 2.5, 'dose_unit' => 'mg', 'route' => 'subq', 'frequency' => 'twice weekly', 'population' => 'animal', 'evidence_strength' => 'preclinical', 'study_title' => 'Thymosin beta-4 promotes dermal healing in rats', 'study_year' => 2007, 'citation_pubmed_id' => '17584560' ],
			[ 'peptide' => 'tb-500', 'dose_min' => 5, 'dose_max' => 5, 'dose_unit' => 'mg', 'route' => 'subq', 'frequency' => 'weekly', 'population' => 'animal', 'evidence_strength' => 'preclinical', 'study_title' => 'TB4 cardiac repair in murine MI model', 'study_year' => 2012, 'citation_pubmed_id' => '22561753' ],
		];

		$count = 0;
		foreach ( $rows as $row ) {
			$slug = $row['peptide'];
			unset( $row['peptide'] );

			if ( ! isset( $peptide_ids[ $slug ] ) ) {
				continue;
			}

			$row['peptide_id'] = $peptide_ids[ $slug ];
			$row['source']     = 'manual';
			$row['added_by']   = get_current_user_id() ?: 1;

			if ( $repo->insert( $row ) > 0 ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Seed 5 legal cells across BPC-157 and Semaglutide.
	 *
	 * @param array<string, int> $peptide_ids Map of slug => post ID.
	 * @return int Number of cells inserted.
	 */
	private static function seed_legal_cells( array $peptide_ids ): int {
		$repo  = new PR_Core_Legal_Repository();
		$cells = [
			[ 'peptide' => 'bpc-157', 'country_code' => 'US', 'status' => 'ruo', 'regulatory_framework' => 'Not FDA-approved; available as Research Use Only', 'notes' => 'Compounding pharmacies may dispense under FDA 503A/503B exemptions.' ],
			[ 'peptide' => 'bpc-157', 'country_code' => 'GB', 'status' => 'unclear', 'regulatory_framework' => 'Not listed by MHRA', 'notes' => 'No specific scheduling; import for personal use is a grey area.' ],
			[ 'peptide' => 'semaglutide', 'country_code' => 'US', 'status' => 'prescription', 'regulatory_framework' => 'FDA-approved (Ozempic, Wegovy, Rybelsus)', 'regulatory_text_url' => 'https://www.accessdata.fda.gov/drugsatfda_docs/label/2023/209637s020lbl.pdf', 'notes' => 'Schedule IV not applicable; requires valid prescription.' ],
			[ 'peptide' => 'semaglutide', 'country_code' => 'GB', 'status' => 'prescription', 'regulatory_framework' => 'MHRA-approved (Ozempic, Wegovy)', 'notes' => 'Available via NHS and private prescription.' ],
			[ 'peptide' => 'semaglutide', 'country_code' => 'AU', 'status' => 'prescription', 'regulatory_framework' => 'TGA-approved (Ozempic)', 'notes' => 'PBS-listed for type 2 diabetes; Wegovy approval pending as of 2025.' ],
		];

		$count = 0;
		foreach ( $cells as $cell ) {
			$slug = $cell['peptide'];
			unset( $cell['peptide'] );

			if ( ! isset( $peptide_ids[ $slug ] ) ) {
				continue;
			}

			$cell['peptide_id']  = $peptide_ids[ $slug ];
			$cell['reviewer_id'] = get_current_user_id() ?: 1;

			if ( $repo->insert( $cell ) > 0 ) {
				$count++;
			}
		}

		return $count;
	}
}
