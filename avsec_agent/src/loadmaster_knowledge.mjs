// avsec_agent/src/loadmaster_knowledge.mjs
// Basis Pengetahuan Regulasi IATA untuk AI Agent Loadmaster (Tahap 5 & 6)
// Standar: IATA CSC Res. 600b (±5%), IATA TACT (HEA >= 150 kg), IATA ULD Technical Manual (AKE/PMC), Boeing 737-800F WBM

export const IATA_TOLERANCE_PERCENT = 5.0;
export const HEA_THRESHOLD_KG = 150.0;

export const ULD_SPECS = {
  AKE: {
    type: 'AKE',
    name: 'AKE (LD3 Contour Lower Deck Container)',
    tare_weight_kg: 75.0,
    max_gross_weight_kg: 1588.0,
    max_net_payload_kg: 1513.0,
    default_compartment: 'AFT-LOWER-1',
    regulation: 'IATA ULD Technical Manual §3.2 (LD3 Structural Limit)',
  },
  PMC: {
    type: 'PMC',
    name: 'PMC (96x125 inch Main Deck Pallet)',
    tare_weight_kg: 110.0,
    max_gross_weight_kg: 6800.0,
    max_net_payload_kg: 6690.0,
    default_compartment: 'MAIN-DECK-01',
    regulation: 'IATA ULD Technical Manual §4.1 (Main Deck Pallet)',
  },
};

export const AIRCRAFT_WBM = {
  aircraft_type: 'Boeing 737-800F',
  flight_number: 'GA-707',
  max_payload_kg: 18500.0,
  cg_safe_envelope_min_mac: 18.0,
  cg_safe_envelope_max_mac: 34.0,
  optimal_cg_mac: 28.4,
  oiml_scale_standard: 'OIML R76 Class III (±1.0 kg)',
  tolerance_regulation: 'IATA CSC Resolution 600b (±5.0% Weight Tolerance)',
  hea_regulation: 'IATA TACT Rules §5.7 (HEA >= 150 kg per piece)',
};

// Profil rasio wajar berat rata-rata per koli berdasarkan jenis komoditas (untuk deteksi anomali muatan)
export const COMMODITY_PIECE_WEIGHT_PROFILES = {
  PHARMA: { label: 'Farmasi / Cold Chain', typicalMaxPerPieceKg: 120 },
  PERISHABLE: { label: 'Perishable / Makanan Segar', typicalMaxPerPieceKg: 100 },
  ELECTRONICS: { label: 'Elektronik & Semikonduktor', typicalMaxPerPieceKg: 180 },
  GENERAL_CARGO: { label: 'General Cargo / Tekstil / Industri', typicalMaxPerPieceKg: 500 },
  DANGEROUS_GOODS: { label: 'Dangerous Goods', typicalMaxPerPieceKg: 250 },
};
