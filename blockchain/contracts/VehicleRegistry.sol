// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * @title VehicleRegistry
 * @notice Autochain Emma+ — registre on-chain des preuves véhicule.
 * @dev Aucune donnée nominative (RGPD) : uniquement technicalId + hashes.
 * Auteur : Lass
 */
contract VehicleRegistry {
    struct VehicleProof {
        bytes32 technicalId;
        bytes32 currentHash;
        uint256 mileage;
        uint64 updatedAt;
        bool exists;
    }

    struct MaintenanceProof {
        bytes32 technicalId;
        bytes32 payloadHash;
        uint256 mileage;
        uint64 performedAt;
    }

    address public admin;
    mapping(bytes32 => VehicleProof) public vehicles;
    mapping(bytes32 => MaintenanceProof[]) private maintenances;
    mapping(bytes32 => bool) public usedPayloadHashes;

    event VehicleRegistered(bytes32 indexed technicalId, bytes32 vinHash, uint256 mileage);
    event MileageCertified(bytes32 indexed technicalId, uint256 mileage, bytes32 payloadHash);
    event MaintenanceCertified(bytes32 indexed technicalId, bytes32 payloadHash, uint256 mileage);
    event SensitiveActionExecuted(
        bytes32 indexed technicalId,
        bytes32 actionHash,
        address adminSigner,
        address buyerSigner
    );

    modifier onlyAdmin() {
        require(msg.sender == admin, "Admin only");
        _;
    }

    constructor() {
        admin = msg.sender;
    }

    function transferAdmin(address newAdmin) external onlyAdmin {
        require(newAdmin != address(0), "Invalid admin");
        admin = newAdmin;
    }

    function registerVehicle(
        bytes32 technicalId,
        bytes32 vinHash,
        bytes32 registrationHash,
        uint256 mileage
    ) external onlyAdmin {
        require(!vehicles[technicalId].exists, "Already registered");

        bytes32 initialHash = keccak256(
            abi.encodePacked(technicalId, vinHash, registrationHash, mileage, block.timestamp)
        );

        vehicles[technicalId] = VehicleProof({
            technicalId: technicalId,
            currentHash: initialHash,
            mileage: mileage,
            updatedAt: uint64(block.timestamp),
            exists: true
        });

        emit VehicleRegistered(technicalId, vinHash, mileage);
    }

    function certifyMileage(
        bytes32 technicalId,
        uint256 newMileage,
        bytes32 payloadHash
    ) external onlyAdmin {
        VehicleProof storage vehicle = vehicles[technicalId];
        require(vehicle.exists, "Unknown vehicle");
        require(newMileage >= vehicle.mileage, "Mileage fraud");
        require(!usedPayloadHashes[payloadHash], "Replay");

        usedPayloadHashes[payloadHash] = true;
        vehicle.mileage = newMileage;
        vehicle.currentHash = payloadHash;
        vehicle.updatedAt = uint64(block.timestamp);

        emit MileageCertified(technicalId, newMileage, payloadHash);
    }

    function certifyMaintenance(
        bytes32 technicalId,
        uint256 mileageAtService,
        bytes32 payloadHash
    ) external onlyAdmin {
        VehicleProof storage vehicle = vehicles[technicalId];
        require(vehicle.exists, "Unknown vehicle");
        require(!usedPayloadHashes[payloadHash], "Replay");

        usedPayloadHashes[payloadHash] = true;
        vehicle.currentHash = payloadHash;
        vehicle.updatedAt = uint64(block.timestamp);

        maintenances[technicalId].push(
            MaintenanceProof({
                technicalId: technicalId,
                payloadHash: payloadHash,
                mileage: mileageAtService,
                performedAt: uint64(block.timestamp)
            })
        );

        emit MaintenanceCertified(technicalId, payloadHash, mileageAtService);
    }

    function executeSensitiveAction(
        bytes32 technicalId,
        bytes32 actionHash,
        address buyerSigner
    ) external onlyAdmin {
        require(vehicles[technicalId].exists, "Unknown vehicle");
        require(buyerSigner != address(0) && buyerSigner != admin, "Invalid buyer");
        require(!usedPayloadHashes[actionHash], "Replay");

        usedPayloadHashes[actionHash] = true;
        vehicles[technicalId].currentHash = actionHash;
        vehicles[technicalId].updatedAt = uint64(block.timestamp);

        emit SensitiveActionExecuted(technicalId, actionHash, admin, buyerSigner);
    }

    function getMaintenanceCount(bytes32 technicalId) external view returns (uint256) {
        return maintenances[technicalId].length;
    }

    function getMaintenance(
        bytes32 technicalId,
        uint256 index
    ) external view returns (MaintenanceProof memory) {
        return maintenances[technicalId][index];
    }
}
