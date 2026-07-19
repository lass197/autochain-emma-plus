const fs = require('fs');
const path = require('path');
const hre = require('hardhat');

async function main() {
    const [deployer] = await hre.ethers.getSigners();
    console.log('Deployer:', deployer.address);

    const Registry = await hre.ethers.getContractFactory('VehicleRegistry');
    const registry = await Registry.deploy();
    await registry.waitForDeployment();

    const address = await registry.getAddress();
    console.log('VehicleRegistry deployed to:', address);

    const outDir = path.join(__dirname, '..', 'deployments');
    fs.mkdirSync(outDir, { recursive: true });

    const artifact = await hre.artifacts.readArtifact('VehicleRegistry');
    const payload = {
        network: hre.network.name,
        chainId: (await hre.ethers.provider.getNetwork()).chainId.toString(),
        contractAddress: address,
        deployer: deployer.address,
        abi: artifact.abi,
        deployedAt: new Date().toISOString(),
    };

    fs.writeFileSync(
        path.join(outDir, `${hre.network.name}.json`),
        JSON.stringify(payload, null, 2),
    );

    // Copie ABI côté Laravel
    const laravelAbiDir = path.join(__dirname, '..', '..', 'storage', 'app', 'blockchain');
    fs.mkdirSync(laravelAbiDir, { recursive: true });
    fs.writeFileSync(
        path.join(laravelAbiDir, 'VehicleRegistry.json'),
        JSON.stringify(payload, null, 2),
    );

    console.log('ABI exporté vers storage/app/blockchain/VehicleRegistry.json');
    console.log('\nAjoute dans .env Laravel :');
    console.log(`AUTOCHAIN_CONTRACT_ADDRESS=${address}`);
    console.log('AUTOCHAIN_RPC_URL=http://127.0.0.1:8545');
    console.log('AUTOCHAIN_CHAIN_ID=31337');
    console.log('AUTOCHAIN_OPERATOR_PRIVATE_KEY=<clé privée du compte Hardhat #0>');
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
