const { expect } = require('chai');
const { ethers } = require('hardhat');

describe('VehicleRegistry', function () {
    it('enregistre un véhicule et certifie le kilométrage', async function () {
        const Registry = await ethers.getContractFactory('VehicleRegistry');
        const registry = await Registry.deploy();

        const technicalId = ethers.id('vehicle-uuid-1');
        const vinHash = ethers.id('VINHASH');
        const regHash = ethers.id('REGHASH');

        await registry.registerVehicle(technicalId, vinHash, regHash, 1000);
        const proof = await registry.vehicles(technicalId);
        expect(proof.exists).to.equal(true);
        expect(proof.mileage).to.equal(1000n);

        const payloadHash = ethers.id('mileage-payload');
        await registry.certifyMileage(technicalId, 1500, payloadHash);
        const updated = await registry.vehicles(technicalId);
        expect(updated.mileage).to.equal(1500n);
    });

    it('rejette une baisse de kilométrage', async function () {
        const Registry = await ethers.getContractFactory('VehicleRegistry');
        const registry = await Registry.deploy();

        const technicalId = ethers.id('vehicle-uuid-2');
        await registry.registerVehicle(technicalId, ethers.id('a'), ethers.id('b'), 2000);

        await expect(
            registry.certifyMileage(technicalId, 1800, ethers.id('bad')),
        ).to.be.revertedWith('Mileage fraud');
    });
});
