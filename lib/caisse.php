<?php
// lib/caisse.php – caisse unifiée sur journal_caisse

// Normalise le sens (ENTREE/SORTIE → RECETTE/DEPENSE)
function caisse_normaliser_sens(string $sens): string
{
	$s = strtoupper(trim($sens));
	if ($s === 'ENTREE') {
		return 'RECETTE';
	}
	if ($s === 'SORTIE') {
		return 'DEPENSE';
	}
	if (!in_array($s, ['RECETTE', 'DEPENSE'], true)) {
		throw new InvalidArgumentException('Sens invalide (attendu ENTREE/SORTIE/RECETTE/DEPENSE)');
	}
	return $s;
}

/**
 * Enregistre une écriture dans journal_caisse (source de vérité unique).
 * Paramètres additionnels optionnels pour coller au schéma existant.
 */
function caisse_enregistrer_ecriture(
	PDO $pdo,
	string $sens,
	float $montant,
	?string $source_type = null,
	?int $source_id = null,
	?string $commentaire = null,
	?int $utilisateur_id = null,
	?string $date_operation = null,
	?string $numero_piece = null,
	?int $mode_paiement_id = 1,
	?string $type_operation = null,
	?int $vente_id = null,
	?int $reservation_id = null,
	?int $inscription_formation_id = null,
	?int $client_id = null,
	?int $fournisseur_id = null
): int {
	$sensNorm = caisse_normaliser_sens($sens);

	$dateOp = $date_operation ?: date('Y-m-d');
	$numero = $numero_piece ?: 'CAISSE-' . date('Ymd-His') . '-' . random_int(100, 999);
	$typeOp = $type_operation ?: ($source_type ? strtoupper($source_type) : ($sensNorm === 'RECETTE' ? 'VENTE' : 'AUTRE'));

	// Valeurs obligatoires du schéma
	$modePaiementId = $mode_paiement_id ?: 1;
	$responsableId = $utilisateur_id ?: 1;

	$sql = "
		INSERT INTO journal_caisse (
			date_operation,
			numero_piece,
			nature_operation,
			client_id,
			fournisseur_id,
			sens,
			montant,
			mode_paiement_id,
			vente_id,
			reservation_id,
			inscription_formation_id,
			responsable_encaissement_id,
			observations,
			est_annule,
			type_operation,
			date_annulation,
			annule_par_id
		) VALUES (
			:date_operation,
			:numero_piece,
			:nature_operation,
			:client_id,
			:fournisseur_id,
			:sens,
			:montant,
			:mode_paiement_id,
			:vente_id,
			:reservation_id,
			:inscription_formation_id,
			:responsable_encaissement_id,
			:observations,
			0,
			:type_operation,
			NULL,
			NULL
		)
	";

	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		':date_operation' => $dateOp,
		':numero_piece' => $numero,
		':nature_operation' => $commentaire,
		':client_id' => $client_id,
		':fournisseur_id' => $fournisseur_id,
		':sens' => $sensNorm,
		':montant' => $montant,
		':mode_paiement_id' => $modePaiementId,
		':vente_id' => $vente_id,
		':reservation_id' => $reservation_id,
		':inscription_formation_id' => $inscription_formation_id,
		':responsable_encaissement_id' => $responsableId,
		':observations' => $commentaire,
		':type_operation' => $typeOp,
	]);

	return (int)$pdo->lastInsertId();
}

function caisse_annuler_ecriture(PDO $pdo, int $id, ?int $annule_par_id = null): bool
{
	$stmt = $pdo->prepare("UPDATE journal_caisse SET est_annule = 1, date_annulation = NOW(), annule_par_id = :annule_par_id WHERE id = :id");
	return $stmt->execute([
		':annule_par_id' => $annule_par_id,
		':id' => $id,
	]);
}

function caisse_get_solde(PDO $pdo, ?string $date_debut = null, ?string $date_fin = null): float
{
	$sql = "SELECT COALESCE(SUM(CASE WHEN sens = 'RECETTE' THEN montant ELSE -montant END), 0) AS solde FROM journal_caisse WHERE est_annule = 0";
	$params = [];
	if ($date_debut) {
		$sql .= " AND date_operation >= :date_debut";
		$params[':date_debut'] = $date_debut;
	}
	if ($date_fin) {
		$sql .= " AND date_operation <= :date_fin";
		$params[':date_fin'] = $date_fin;
	}
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return (float)($row['solde'] ?? 0);
}

function caisse_get_recent(PDO $pdo, int $limit = 50): array
{
	$stmt = $pdo->prepare('SELECT * FROM journal_caisse ORDER BY id DESC LIMIT :l');
	$stmt->bindValue(':l', $limit, PDO::PARAM_INT);
	$stmt->execute();
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

