<?php
declare(strict_types=1);

/**
 * Static seed data fixtures for development and testing.
 *
 * What: Provides hard-coded peptide, dosing, and legal data used by PR_Core_Seed_Data.
 * Who calls it: PR_Core_Seed_Data — calls get_peptides(), get_dosing_rows(), get_legal_cells().
 * Dependencies: None.
 *
 * @see class-pr-core-seed-data.php — Orchestrator that consumes these fixtures.
 * @package Peptide_Repo_Core
 */
class PR_Core_Seed_Fixtures {

	/**
	 * Return the canonical list of peptide fixture definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_peptides(): array {
		return array(
			array(
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
			),
			array(
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
			),
			array(
				'title'             => 'TB-500',
				'slug'              => 'tb-500',
				'excerpt'           => 'Thymosin Beta-4 fragment, a synthetic peptide used in tissue repair research.',
				'display_name'      => 'TB-500',
				'aliases'           => '["Thymosin Beta-4","T\u03b24"]',
				'molecular_formula' => 'C212H350N56O78S',
				'molecular_weight'  => 4963.44,
				'cas_number'        => '77591-33-4',
				'drugbank_id'       => '',
				'chembl_id'         => '',
				'evidence_strength' => 'preclinical',
			),
		);
	}

	/**
	 * Return the canonical list of dosing row fixture definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_dosing_rows(): array {
		return array(
			array(
				'peptide'            => 'bpc-157',
				'dose_min'           => 200,
				'dose_max'           => 300,
				'dose_unit'          => 'mcg',
				'route'              => 'subq',
				'frequency'          => 'twice daily',
				'population'         => 'healthy',
				'evidence_strength'  => 'observational',
				'study_title'        => 'BPC 157 in wound healing review',
				'study_year'         => 2021,
				'citation_pubmed_id' => '34537800',
			),
			array(
				'peptide'            => 'bpc-157',
				'dose_min'           => 500,
				'dose_max'           => 500,
				'dose_unit'          => 'mcg',
				'route'              => 'subq',
				'frequency'          => 'daily',
				'population'         => 'healthy',
				'evidence_strength'  => 'case-series',
				'study_title'        => 'Subcutaneous BPC-157 in tendon repair',
				'study_year'         => 2019,
				'citation_pubmed_id' => '30915550',
			),
			array(
				'peptide'            => 'bpc-157',
				'dose_min'           => 10,
				'dose_max'           => 10,
				'dose_unit'          => 'mcg',
				'route'              => 'oral',
				'frequency'          => 'daily',
				'population'         => 'animal',
				'evidence_strength'  => 'preclinical',
				'study_title'        => 'Oral BPC-157 in rat gastric ulcer model',
				'study_year'         => 2018,
				'citation_pubmed_id' => '29869189',
			),
			array(
				'peptide'            => 'bpc-157',
				'dose_min'           => 250,
				'dose_max'           => 750,
				'dose_unit'          => 'mcg',
				'route'              => 'im',
				'frequency'          => 'daily',
				'population'         => 'clinical',
				'evidence_strength'  => 'case-series',
				'study_title'        => 'Intramuscular BPC-157 for muscle injuries',
				'study_year'         => 2020,
				'citation_pubmed_id' => '33312000',
			),
			array(
				'peptide'            => 'semaglutide',
				'dose_min'           => 0.25,
				'dose_max'           => 0.25,
				'dose_unit'          => 'mg',
				'route'              => 'subq',
				'frequency'          => 'weekly',
				'population'         => 'clinical',
				'evidence_strength'  => 'rct-large',
				'study_title'        => 'SUSTAIN 1: Semaglutide in T2DM',
				'study_year'         => 2017,
				'citation_pubmed_id' => '28930514',
				'indication'         => 'Type 2 diabetes initiation dose',
			),
			array(
				'peptide'            => 'semaglutide',
				'dose_min'           => 1.0,
				'dose_max'           => 1.0,
				'dose_unit'          => 'mg',
				'route'              => 'subq',
				'frequency'          => 'weekly',
				'population'         => 'clinical',
				'evidence_strength'  => 'rct-large',
				'study_title'        => 'SUSTAIN 6: CV outcomes with semaglutide',
				'study_year'         => 2016,
				'citation_pubmed_id' => '27633186',
				'indication'         => 'Type 2 diabetes maintenance',
			),
			array(
				'peptide'            => 'semaglutide',
				'dose_min'           => 2.4,
				'dose_max'           => 2.4,
				'dose_unit'          => 'mg',
				'route'              => 'subq',
				'frequency'          => 'weekly',
				'population'         => 'clinical',
				'evidence_strength'  => 'rct-large',
				'study_title'        => 'STEP 1: Semaglutide 2.4mg for obesity',
				'study_year'         => 2021,
				'citation_pubmed_id' => '33567185',
				'indication'         => 'Chronic weight management',
			),
			array(
				'peptide'            => 'semaglutide',
				'dose_min'           => 14,
				'dose_max'           => 14,
				'dose_unit'          => 'mg',
				'route'              => 'oral',
				'frequency'          => 'daily',
				'population'         => 'clinical',
				'evidence_strength'  => 'rct-large',
				'study_title'        => 'PIONEER 1: Oral semaglutide in T2DM',
				'study_year'         => 2019,
				'citation_pubmed_id' => '30924169',
				'indication'         => 'Type 2 diabetes oral formulation',
			),
			array(
				'peptide'            => 'tb-500',
				'dose_min'           => 2,
				'dose_max'           => 2.5,
				'dose_unit'          => 'mg',
				'route'              => 'subq',
				'frequency'          => 'twice weekly',
				'population'         => 'animal',
				'evidence_strength'  => 'preclinical',
				'study_title'        => 'Thymosin beta-4 promotes dermal healing in rats',
				'study_year'         => 2007,
				'citation_pubmed_id' => '17584560',
			),
			array(
				'peptide'            => 'tb-500',
				'dose_min'           => 5,
				'dose_max'           => 5,
				'dose_unit'          => 'mg',
				'route'              => 'subq',
				'frequency'          => 'weekly',
				'population'         => 'animal',
				'evidence_strength'  => 'preclinical',
				'study_title'        => 'TB4 cardiac repair in murine MI model',
				'study_year'         => 2012,
				'citation_pubmed_id' => '22561753',
			),
		);
	}

	/**
	 * Return the canonical list of legal cell fixture definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_legal_cells(): array {
		return array(
			array(
				'peptide'              => 'bpc-157',
				'country_code'         => 'US',
				'status'               => 'ruo',
				'regulatory_framework' => 'Not FDA-approved; available as Research Use Only',
				'notes'                => 'Compounding pharmacies may dispense under FDA 503A/503B exemptions.',
			),
			array(
				'peptide'              => 'bpc-157',
				'country_code'         => 'GB',
				'status'               => 'unclear',
				'regulatory_framework' => 'Not listed by MHRA',
				'notes'                => 'No specific scheduling; import for personal use is a grey area.',
			),
			array(
				'peptide'              => 'semaglutide',
				'country_code'         => 'US',
				'status'               => 'prescription',
				'regulatory_framework' => 'FDA-approved (Ozempic, Wegovy, Rybelsus)',
				'regulatory_text_url'  => 'https://www.accessdata.fda.gov/drugsatfda_docs/label/2023/209637s020lbl.pdf',
				'notes'                => 'Schedule IV not applicable; requires valid prescription.',
			),
			array(
				'peptide'              => 'semaglutide',
				'country_code'         => 'GB',
				'status'               => 'prescription',
				'regulatory_framework' => 'MHRA-approved (Ozempic, Wegovy)',
				'notes'                => 'Available via NHS and private prescription.',
			),
			array(
				'peptide'              => 'semaglutide',
				'country_code'         => 'AU',
				'status'               => 'prescription',
				'regulatory_framework' => 'TGA-approved (Ozempic)',
				'notes'                => 'PBS-listed for type 2 diabetes; Wegovy approval pending as of 2025.',
			),
		);
	}
}
