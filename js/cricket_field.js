/**
 * CricketField3D
 * Reusable Three.js & GSAP class to render and animate a live cricket ground.
 */
class CricketField3D {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error(`Container #${containerId} not found`);
            return;
        }

        this.options = Object.assign({
            isInteractive: true,
            isMini: false,
            theme: 'dark'
        }, options);

        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;
        this.players = {};
        this.playerLabels = {};
        this.stumps = { striker: [], bowler: [] };
        this.bails = { striker: [], bowler: [] };
        this.ball = null;
        this.ballTrail = null;
        this.fieldSetup = 'normal';
        this.activeInnings = 1;
        this.lastBallId = null;
        this.currentOverNum = null;
        this.isAnimating = false;

        // Parent group for rotating entire field on over completion
        this.fieldGroup = null;

        // Colors
        this.colors = {
            grassLight: 0x1e4620,
            grassDark: 0x143216,
            pitch: 0xc2a679,
            crease: 0xffffff,
            wicket: 0x8d5c34,
            ball: 0xffffff,
            boundary: 0x0f2b5c,
            floodlight: 0xffffff,
            battingTeam: 0xffc107, // Gold
            bowlingTeam: 0x0dcaf0  // Neon Blue
        };

        // Fielder positions for each preset (X, Z)
        this.fieldPresets = {
            normal: {
                keeper: { x: 0, z: 15, name: 'Wicket Keeper', role: 'keeper' },
                bowler: { x: 0, z: -10, name: 'Bowler', role: 'bowler' },
                slip: { x: 2, z: 12, name: 'First Slip', role: 'fielder' },
                point: { x: -22, z: 2, name: 'Point', role: 'fielder' },
                cover: { x: -20, z: -10, name: 'Cover', role: 'fielder' },
                midOff: { x: -10, z: -20, name: 'Mid Off', role: 'fielder' },
                midOn: { x: 10, z: -20, name: 'Mid On', role: 'fielder' },
                midWicket: { x: 20, z: -10, name: 'Mid Wicket', role: 'fielder' },
                squareLeg: { x: 22, z: 2, name: 'Square Leg', role: 'fielder' },
                fineLeg: { x: 15, z: 25, name: 'Fine Leg', role: 'fielder' },
                thirdMan: { x: -15, z: 25, name: 'Third Man', role: 'fielder' }
            },
            powerplay: {
                keeper: { x: 0, z: 15, name: 'Wicket Keeper', role: 'keeper' },
                bowler: { x: 0, z: -10, name: 'Bowler', role: 'bowler' },
                slip: { x: 2, z: 12, name: 'First Slip', role: 'fielder' },
                point: { x: -18, z: 2, name: 'Point', role: 'fielder' },
                cover: { x: -15, z: -6, name: 'Cover', role: 'fielder' },
                midOff: { x: -10, z: -18, name: 'Mid Off', role: 'fielder' },
                midOn: { x: 10, z: -18, name: 'Mid On', role: 'fielder' },
                midWicket: { x: 15, z: -6, name: 'Mid Wicket', role: 'fielder' },
                squareLeg: { x: 18, z: 2, name: 'Square Leg', role: 'fielder' },
                fineLeg: { x: 35, z: 42, name: 'Deep Fine Leg', role: 'fielder' },
                thirdMan: { x: -40, z: 35, name: 'Deep Third Man', role: 'fielder' }
            },
            defensive: {
                keeper: { x: 0, z: 15, name: 'Wicket Keeper', role: 'keeper' },
                bowler: { x: 0, z: -10, name: 'Bowler', role: 'bowler' },
                slip: { x: 3, z: 13, name: 'First Slip', role: 'fielder' },
                point: { x: -24, z: 2, name: 'Point', role: 'fielder' },
                deepCover: { x: -45, z: -25, name: 'Deep Cover', role: 'fielder' },
                longOff: { x: -25, z: -48, name: 'Long Off', role: 'fielder' },
                longOn: { x: 25, z: -48, name: 'Long On', role: 'fielder' },
                deepMidWicket: { x: 45, z: -25, name: 'Deep Mid Wicket', role: 'fielder' },
                deepSquareLeg: { x: 48, z: 5, name: 'Deep Square Leg', role: 'fielder' },
                fineLeg: { x: 18, z: 22, name: 'Fine Leg', role: 'fielder' },
                thirdMan: { x: -20, z: 22, name: 'Third Man', role: 'fielder' }
            },
            attacking: {
                keeper: { x: 0, z: 14, name: 'Wicket Keeper', role: 'keeper' },
                bowler: { x: 0, z: -10, name: 'Bowler', role: 'bowler' },
                slip1: { x: 1.8, z: 12.5, name: 'First Slip', role: 'fielder' },
                slip2: { x: 3.5, z: 12.0, name: 'Second Slip', role: 'fielder' },
                gully: { x: -5, z: 10, name: 'Gully', role: 'fielder' },
                sillyMidOff: { x: -5, z: -5, name: 'Silly Mid Off', role: 'fielder' },
                sillyMidOn: { x: 5, z: -5, name: 'Silly Mid On', role: 'fielder' },
                shortLeg: { x: 4, z: 8, name: 'Short Leg', role: 'fielder' },
                point: { x: -20, z: 2, name: 'Point', role: 'fielder' },
                midOff: { x: -10, z: -20, name: 'Mid Off', role: 'fielder' },
                midOn: { x: 10, z: -20, name: 'Mid On', role: 'fielder' }
            },
            custom: {
                keeper: { x: 0, z: 15, name: 'Wicket Keeper', role: 'keeper' },
                bowler: { x: 0, z: -10, name: 'Bowler', role: 'bowler' },
                slip: { x: 2, z: 12, name: 'First Slip', role: 'fielder' },
                point: { x: -22, z: 2, name: 'Point', role: 'fielder' },
                cover: { x: -30, z: -15, name: 'Deep Cover', role: 'fielder' },
                midOff: { x: -15, z: -35, name: 'Long Off', role: 'fielder' },
                midOn: { x: 15, z: -35, name: 'Long On', role: 'fielder' },
                midWicket: { x: 30, z: -15, name: 'Deep Mid Wicket', role: 'fielder' },
                squareLeg: { x: 22, z: 2, name: 'Square Leg', role: 'fielder' },
                fineLeg: { x: 30, z: 35, name: 'Deep Fine Leg', role: 'fielder' },
                thirdMan: { x: -30, z: 35, name: 'Deep Third Man', role: 'fielder' }
            }
        };

        this.init();
    }

    init() {
        // Create HTML Overlay container for labels
        this.labelOverlay = document.createElement('div');
        this.labelOverlay.className = 'field3d-label-overlay';
        this.labelOverlay.style.position = 'absolute';
        this.labelOverlay.style.top = '0';
        this.labelOverlay.style.left = '0';
        this.labelOverlay.style.width = '100%';
        this.labelOverlay.style.height = '100%';
        this.labelOverlay.style.pointerEvents = 'none';
        this.labelOverlay.style.overflow = 'hidden';
        this.container.style.position = 'relative';
        this.container.appendChild(this.labelOverlay);

        // Three.js Setup
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x0a0f12);
        this.scene.fog = new THREE.FogExp2(0x0a0f12, 0.008);

        const width = this.container.clientWidth || 400;
        const height = this.container.clientHeight || 300;

        // Camera
        this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
        this.setCameraView('broadcast');

        // Renderer
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.container.appendChild(this.renderer.domElement);

        // Controls
        if (this.options.isInteractive) {
            this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
            this.controls.enableDamping = true;
            this.controls.dampingFactor = 0.05;
            this.controls.maxPolarAngle = Math.PI / 2 - 0.02; // Don't go below ground
            this.controls.minDistance = 10;
            this.controls.maxDistance = 200;
        }

        // Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.4);
        this.scene.add(ambientLight);

        const mainLight = new THREE.DirectionalLight(0xffffff, 0.8);
        mainLight.position.set(30, 80, -20);
        mainLight.castShadow = true;
        mainLight.shadow.mapSize.width = 2048;
        mainLight.shadow.mapSize.height = 2048;
        mainLight.shadow.camera.near = 0.5;
        mainLight.shadow.camera.far = 250;
        const d = 80;
        mainLight.shadow.camera.left = -d;
        mainLight.shadow.camera.right = d;
        mainLight.shadow.camera.top = d;
        mainLight.shadow.camera.bottom = -d;
        mainLight.shadow.bias = -0.0005;
        this.scene.add(mainLight);

        // Initialize parent Field Group
        this.fieldGroup = new THREE.Group();
        this.scene.add(this.fieldGroup);

        // Build Field inside fieldGroup
        this.buildGround();
        this.buildPitch();
        this.buildWickets();
        this.buildBoundaryAndStadium();
        this.buildBall();
        this.setupInitialPlayers();

        // Resize Listener
        window.addEventListener('resize', this.onResize.bind(this));

        // Start Loop
        this.animate();
    }

    buildGround() {
        const radius = 60;
        const segments = 64;

        // Generate procedural canvas grass texture
        const canvas = document.createElement('canvas');
        canvas.width = 512;
        canvas.height = 512;
        const ctx = canvas.getContext('2d');
        
        for (let i = 0; i < 20; i++) {
            ctx.fillStyle = i % 2 === 0 ? '#183c1a' : '#143015';
            ctx.beginPath();
            ctx.arc(256, 256, 256 - (i * 12.8), 0, Math.PI * 2);
            ctx.fill();
        }
        
        const texture = new THREE.CanvasTexture(canvas);
        texture.wrapS = THREE.RepeatWrapping;
        texture.wrapT = THREE.RepeatWrapping;
        texture.repeat.set(1, 1);

        const groundGeom = new THREE.CylinderGeometry(radius, radius, 1, segments);
        const groundMat = new THREE.MeshStandardMaterial({
            map: texture,
            roughness: 0.8,
            metalness: 0.1
        });

        const ground = new THREE.Mesh(groundGeom, groundMat);
        ground.position.y = -0.5;
        ground.receiveShadow = true;
        this.fieldGroup.add(ground);

        // 30 Yard Circle Line
        const ringGeom = new THREE.RingGeometry(30, 30.3, 64);
        ringGeom.rotateX(-Math.PI / 2);
        const ringMat = new THREE.MeshBasicMaterial({
            color: 0xffffff,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.25
        });
        const yardCircle = new THREE.Mesh(ringGeom, ringMat);
        yardCircle.position.y = 0.01;
        this.fieldGroup.add(yardCircle);
    }

    buildPitch() {
        const pitchGeom = new THREE.PlaneGeometry(4, 24);
        pitchGeom.rotateX(-Math.PI / 2);
        
        const pitchMat = new THREE.MeshStandardMaterial({
            color: this.colors.pitch,
            roughness: 0.9,
            metalness: 0.05
        });
        const pitch = new THREE.Mesh(pitchGeom, pitchMat);
        pitch.position.y = 0.01;
        pitch.receiveShadow = true;
        this.fieldGroup.add(pitch);

        const creaseMat = new THREE.MeshBasicMaterial({ color: this.colors.crease });
        
        // Striking crease
        const creaseGeom1 = new THREE.PlaneGeometry(4, 0.1);
        creaseGeom1.rotateX(-Math.PI / 2);
        const crease1 = new THREE.Mesh(creaseGeom1, creaseMat);
        crease1.position.set(0, 0.015, 10);
        this.fieldGroup.add(crease1);

        // Bowling crease
        const creaseGeom2 = new THREE.PlaneGeometry(4, 0.1);
        creaseGeom2.rotateX(-Math.PI / 2);
        const crease2 = new THREE.Mesh(creaseGeom2, creaseMat);
        crease2.position.set(0, 0.015, -10);
        this.fieldGroup.add(crease2);
    }

    buildWickets() {
        const stumpRadius = 0.03;
        const stumpHeight = 0.8;
        const stumpGeom = new THREE.CylinderGeometry(stumpRadius, stumpRadius, stumpHeight, 8);
        const wicketMat = new THREE.MeshStandardMaterial({
            color: this.colors.wicket,
            roughness: 0.6
        });

        const bailRadius = 0.015;
        const bailLength = 0.14;
        const bailGeom = new THREE.CylinderGeometry(bailRadius, bailRadius, bailLength, 8);
        bailGeom.rotateZ(Math.PI / 2);

        const setupEnd = (z, key) => {
            const group = new THREE.Group();
            
            // 3 stumps
            const xOffsets = [-0.15, 0, 0.15];
            xOffsets.forEach(x => {
                const stump = new THREE.Mesh(stumpGeom, wicketMat);
                stump.position.set(x, stumpHeight / 2, 0);
                stump.castShadow = true;
                group.add(stump);
                this.stumps[key].push(stump);
            });

            // 2 bails
            const bailOffsets = [-0.075, 0.075];
            bailOffsets.forEach(x => {
                const bail = new THREE.Mesh(bailGeom, wicketMat);
                bail.position.set(x, stumpHeight + bailRadius, 0);
                group.add(bail);
                this.bails[key].push(bail);
            });

            group.position.set(0, 0, z);
            this.fieldGroup.add(group);
            
            if (key === 'striker') {
                this.strikerWicketsGroup = group;
            } else {
                this.bowlerWicketsGroup = group;
            }
        };

        setupEnd(10.2, 'striker');
        setupEnd(-10.2, 'bowler');
    }

    buildBoundaryAndStadium() {
        // Boundary Rope
        const boundaryGeom = new THREE.TorusGeometry(55, 0.3, 16, 100);
        boundaryGeom.rotateX(Math.PI / 2);
        const boundaryMat = new THREE.MeshStandardMaterial({
            color: this.colors.boundary,
            roughness: 0.5
        });
        const boundary = new THREE.Mesh(boundaryGeom, boundaryMat);
        boundary.position.y = 0.1;
        this.fieldGroup.add(boundary);

        // Stadium Stands Outer ring
        const standGeom = new THREE.TorusGeometry(72, 8, 8, 48);
        standGeom.rotateX(Math.PI / 2);
        const standMat = new THREE.MeshStandardMaterial({
            color: 0x11161d,
            roughness: 0.9,
            metalness: 0.2
        });
        const stands = new THREE.Mesh(standGeom, standMat);
        stands.position.y = -2;
        this.fieldGroup.add(stands);

        // Ad Boards
        const adGeom = new THREE.BoxGeometry(4, 1.2, 0.2);
        const adMat = new THREE.MeshStandardMaterial({
            color: 0x1b2735,
            emissive: 0x0c1e36,
            roughness: 0.4
        });
        for (let i = 0; i < 24; i++) {
            const angle = (i / 24) * Math.PI * 2;
            const r = 54;
            const x = Math.cos(angle) * r;
            const z = Math.sin(angle) * r;
            
            const ad = new THREE.Mesh(adGeom, adMat);
            ad.position.set(x, 0.6, z);
            ad.rotation.y = -angle + Math.PI / 2;
            this.fieldGroup.add(ad);
        }

        // Floodlights
        const towerGeom = new THREE.CylinderGeometry(0.5, 1, 35, 8);
        const towerMat = new THREE.MeshStandardMaterial({ color: 0x2d3748, metalness: 0.7, roughness: 0.2 });
        const lightBoxGeom = new THREE.BoxGeometry(6, 2, 0.5);
        const lightBoxMat = new THREE.MeshStandardMaterial({ color: 0x1a202c });
        
        const lightTowers = [
            { x: -50, z: -50, angle: Math.PI / 4 },
            { x: 50, z: -50, angle: -Math.PI / 4 },
            { x: -50, z: 50, angle: 3 * Math.PI / 4 },
            { x: 50, z: 50, angle: -3 * Math.PI / 4 }
        ];

        lightTowers.forEach(t => {
            const towerGroup = new THREE.Group();
            
            const pole = new THREE.Mesh(towerGeom, towerMat);
            pole.position.y = 17.5;
            pole.castShadow = true;
            towerGroup.add(pole);

            const lightHead = new THREE.Mesh(lightBoxGeom, lightBoxMat);
            lightHead.position.set(0, 35, 0);
            lightHead.rotation.y = t.angle;
            towerGroup.add(lightHead);

            const glowGeom = new THREE.SphereGeometry(0.4, 8, 8);
            const glowMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
            for (let row = -1; row <= 1; row++) {
                for (let col = -2; col <= 2; col++) {
                    const glow = new THREE.Mesh(glowGeom, glowMat);
                    const ox = col * 0.9;
                    const oy = row * 0.6;
                    const rx = ox * Math.cos(t.angle);
                    const rz = -ox * Math.sin(t.angle);
                    glow.position.set(rx, 35 + oy, rz + (0.3 * Math.cos(t.angle)));
                    towerGroup.add(glow);
                }
            }

            towerGroup.position.set(t.x, 0, t.z);
            this.fieldGroup.add(towerGroup);
        });
    }

    buildBall() {
        const ballGeom = new THREE.SphereGeometry(0.12, 16, 16);
        const ballMat = new THREE.MeshBasicMaterial({ color: this.colors.ball });
        this.ball = new THREE.Mesh(ballGeom, ballMat);
        this.ball.position.set(0, -5, 0);
        this.ball.castShadow = true;
        this.fieldGroup.add(this.ball);
    }

    setupInitialPlayers() {
        this.createPlayerMesh('striker', 0, 10, 'Striker', 'striker');
        this.createPlayerMesh('nonStriker', 0, -10, 'Non Striker', 'striker');

        const preset = this.fieldPresets[this.fieldSetup];
        Object.keys(preset).forEach(key => {
            const info = preset[key];
            this.createPlayerMesh(key, info.x, info.z, info.name, info.role);
        });
    }

    createPlayerMesh(id, x, z, labelName, role) {
        const group = new THREE.Group();

        const color = role === 'striker' ? this.colors.battingTeam : this.colors.bowlingTeam;
        const bodyGeom = new THREE.CylinderGeometry(0.4, 0.4, 1.4, 8);
        const bodyMat = new THREE.MeshStandardMaterial({ color: color, roughness: 0.5 });
        const body = new THREE.Mesh(bodyGeom, bodyMat);
        body.position.y = 0.7;
        body.castShadow = true;
        group.add(body);

        const headGeom = new THREE.SphereGeometry(0.3, 8, 8);
        const headMat = new THREE.MeshStandardMaterial({ color: 0xffdbac, roughness: 0.8 });
        const head = new THREE.Mesh(headGeom, headMat);
        head.position.y = 1.6;
        head.castShadow = true;
        group.add(head);

        if (id === 'striker') {
            const batGeom = new THREE.BoxGeometry(0.15, 0.9, 0.05);
            const batMat = new THREE.MeshStandardMaterial({ color: 0xb58050, roughness: 0.6 });
            const bat = new THREE.Mesh(batGeom, batMat);
            bat.position.set(0.4, 0.5, 0.4);
            bat.rotation.set(0.2, 0, -0.4);
            group.add(bat);
            this.batMesh = bat;
        }

        if (id === 'bowler') {
            this.bowlerArmGroup = new THREE.Group();
            const armGeom = new THREE.CylinderGeometry(0.08, 0.08, 0.8, 8);
            const arm = new THREE.Mesh(armGeom, bodyMat);
            arm.position.y = 0.4;
            this.bowlerArmGroup.add(arm);
            this.bowlerArmGroup.position.set(0.5, 1.2, 0);
            group.add(this.bowlerArmGroup);
        }

        group.position.set(x, 0, z);
        this.fieldGroup.add(group);
        this.players[id] = group;

        // Create HTML Label overlay
        const label = document.createElement('div');
        label.className = `player-label ${role}`;
        label.style.position = 'absolute';
        label.style.transform = 'translate(-50%, -100%)';
        label.style.color = '#fff';
        label.style.background = 'rgba(15, 23, 27, 0.85)';
        label.style.border = `1px solid ${role === 'striker' ? '#ffc107' : '#0dcaf0'}`;
        label.style.padding = '3px 8px';
        label.style.borderRadius = '20px';
        label.style.fontSize = '10px';
        label.style.fontWeight = 'bold';
        label.style.whiteSpace = 'nowrap';
        label.style.boxShadow = '0 4px 10px rgba(0,0,0,0.5)';
        label.style.backdropFilter = 'blur(4px)';
        label.style.transition = 'opacity 0.2s';
        label.textContent = labelName;

        this.labelOverlay.appendChild(label);
        this.playerLabels[id] = { element: label, group: group };
    }

    updateState(data) {
        if (!data) return;

        this.activeInnings = data.innings;

        // 1. Sync Names in 2D Overlays
        if (data.next_striker && data.next_striker.name) {
            this.updatePlayerLabel('striker', `${data.next_striker.name} (${data.next_striker.runs}*)`);
        }
        if (data.non_striker && data.non_striker.name) {
            this.updatePlayerLabel('nonStriker', data.non_striker.name);
        }
        if (data.bowler && data.bowler.name) {
            this.updatePlayerLabel('bowler', data.bowler.name);
        }

        // 2. Sync Fielder setup preset positions
        if (data.field_setup && data.field_setup !== this.fieldSetup) {
            this.animateFieldSetupTransition(data.field_setup);
        }

        // 3. Over Completion Rotation
        if (data.score && data.score.overs) {
            const currentOver = Math.floor(parseFloat(data.score.overs));
            if (this.currentOverNum !== null && currentOver !== this.currentOverNum) {
                // Smoothly rotate parent fieldGroup by 180 degrees to switch bowling ends
                const targetRotation = (currentOver % 2) * Math.PI;
                gsap.to(this.fieldGroup.rotation, {
                    y: targetRotation,
                    duration: 2.0,
                    ease: 'power2.inOut'
                });
            }
            this.currentOverNum = currentOver;
        }

        // 4. Trigger Bowling/Shot/Wicket Animation on new Ball ID
        if (data.last_ball_details && data.last_ball_details.id !== this.lastBallId) {
            const isFirstLoad = (this.lastBallId === null);
            this.lastBallId = data.last_ball_details.id;
            
            if (!isFirstLoad && !this.isAnimating) {
                this.triggerLiveBallSequence(data.last_ball_details);
            }
        }
    }

    updatePlayerLabel(id, text) {
        if (this.playerLabels[id]) {
            this.playerLabels[id].element.textContent = text;
        }
    }

    animateFieldSetupTransition(newPreset) {
        if (!this.fieldPresets[newPreset]) return;
        this.fieldSetup = newPreset;
        const preset = this.fieldPresets[newPreset];

        Object.keys(preset).forEach(key => {
            const targetPos = preset[key];
            const playerGroup = this.players[key];
            if (playerGroup) {
                gsap.to(playerGroup.position, {
                    x: targetPos.x,
                    z: targetPos.z,
                    duration: 1.5,
                    ease: 'power2.out'
                });
                this.updatePlayerLabel(key, targetPos.name);
            }
        });
    }

    setCameraView(view) {
        if (!this.camera) return;

        let targetPos, targetLookAt;

        switch (view) {
            case 'broadcast':
                targetPos = { x: 45, y: 35, z: -55 };
                targetLookAt = { x: 0, y: 0, z: 0 };
                break;
            case 'bowler':
                targetPos = { x: 0, y: 6, z: -25 };
                targetLookAt = { x: 0, y: 1, z: 12 };
                break;
            case 'batsman':
                targetPos = { x: 0, y: 6, z: 22 };
                targetLookAt = { x: 0, y: 1, z: -10 };
                break;
            case 'top':
                targetPos = { x: 0.1, y: 90, z: 0 };
                targetLookAt = { x: 0, y: 0, z: 0 };
                break;
            case 'free':
            default:
                return;
        }

        if (this.controls) {
            this.controls.enabled = false;
        }

        gsap.to(this.camera.position, {
            x: targetPos.x,
            y: targetPos.y,
            z: targetPos.z,
            duration: 1.5,
            ease: 'power2.inOut',
            onUpdate: () => {
                this.camera.lookAt(targetLookAt.x, targetLookAt.y, targetLookAt.z);
            },
            onComplete: () => {
                if (this.controls) {
                    this.controls.target.set(targetLookAt.x, targetLookAt.y, targetLookAt.z);
                    this.controls.enabled = (view === 'free' || this.options.isInteractive);
                }
            }
        });
    }

    triggerLiveBallSequence(ballDetails) {
        this.isAnimating = true;

        this.ball.position.set(0, -5, 0);
        this.resetWickets();
        if (this.batMesh) {
            this.batMesh.rotation.set(0.2, 0, -0.4);
        }

        const bowler = this.players['bowler'];
        const striker = this.players['striker'];
        if (!bowler || !striker) {
            this.isAnimating = false;
            return;
        }

        bowler.position.set(0, 0, -18);
        const originalBowlerPos = { x: 0, z: -10 };

        const tl = gsap.timeline({
            onComplete: () => {
                this.isAnimating = false;
            }
        });

        tl.to(bowler.position, {
            z: -10.5,
            duration: 1.0,
            ease: 'none'
        });

        tl.to(this.bowlerArmGroup.rotation, {
            x: -Math.PI * 2,
            duration: 0.3,
            ease: 'power1.in',
            onStart: () => {
                this.ball.position.set(0.5, 1.8, -10.5);
            }
        });

        tl.set(this.bowlerArmGroup.rotation, { x: 0 });

        const runs = ballDetails.runs;
        const extraType = ballDetails.extra_type;
        const isWicket = ballDetails.is_wicket;

        let bounceZ = 2;
        let bounceX = 0;
        let finalX = 0;
        let finalY = 0.2;
        let finalZ = 10;
        let duration = 0.8;

        if (extraType === 'wide') {
            bounceX = 1.5;
            finalX = 3.5;
            finalZ = 14;
            duration = 1.0;
        } else if (isWicket && ballDetails.wicket_type === 'bowled') {
            bounceZ = 4;
            finalX = 0.1;
            finalZ = 10.2;
            duration = 0.75;
        } else if (runs === 0) {
            bounceZ = 1.5;
            finalX = 0.2;
            finalZ = 14;
        } else {
            bounceZ = 2;
            const angle = this.getShotAngleForRuns(runs);
            const distance = runs === 6 ? 65 : (runs === 4 ? 56 : (runs === 3 ? 45 : (runs === 2 ? 30 : 15)));
            finalX = Math.cos(angle) * distance;
            finalZ = Math.sin(angle) * distance;
            if (runs === 6) {
                finalY = 18;
            }
            duration = runs >= 4 ? 1.4 : 1.0;
        }

        const curve = new THREE.QuadraticBezierCurve3(
            new THREE.Vector3(0.5, 1.8, -10.5),
            new THREE.Vector3(bounceX, 0, bounceZ),
            new THREE.Vector3(finalX, finalY, finalZ)
        );

        tl.to(this.ball.position, {
            x: bounceX,
            y: 0.1,
            z: bounceZ,
            duration: duration * 0.4,
            ease: 'power1.in',
            onStart: () => {
                this.drawTrajectoryLine(curve);
            }
        });

        tl.to(this.ball.position, {
            x: finalX,
            y: finalY,
            z: finalZ,
            duration: duration * 0.6,
            ease: runs === 6 ? 'sine.out' : 'power1.out',
            onStart: () => {
                if (this.batMesh && !isWicket) {
                    gsap.to(this.batMesh.rotation, { x: -0.5, y: -0.6, z: 0.8, duration: 0.15 });
                }
            },
            onComplete: () => {
                if (isWicket) {
                    this.explodeStumps();
                } else if (runs > 0) {
                    this.triggerFielderChase(finalX, finalZ, runs);
                    this.triggerBatsmenRuns(runs);
                }
            }
        });

        tl.to(bowler.position, {
            x: originalBowlerPos.x,
            z: originalBowlerPos.z,
            duration: 0.8
        }, "-=0.3");
    }

    getShotAngleForRuns(runs) {
        const angles = {
            1: Math.PI + 0.3,
            2: Math.PI / 4,
            3: -Math.PI / 6,
            4: -Math.PI + 0.5,
            6: Math.PI - 0.5
        };
        return angles[runs] || Math.PI;
    }

    drawTrajectoryLine(curve) {
        if (this.ballTrail) this.fieldGroup.remove(this.ballTrail);

        const points = curve.getPoints(50);
        const geom = new THREE.BufferGeometry().setFromPoints(points);
        const mat = new THREE.LineBasicMaterial({
            color: 0xffffff,
            transparent: true,
            opacity: 0.9,
            linewidth: 3
        });

        this.ballTrail = new THREE.Line(geom, mat);
        this.fieldGroup.add(this.ballTrail);

        gsap.to(mat, {
            opacity: 0,
            duration: 1.0,
            delay: 2.0,
            onComplete: () => {
                this.fieldGroup.remove(this.ballTrail);
                this.ballTrail = null;
            }
        });
    }

    explodeStumps() {
        this.stumps.striker.forEach((stump, idx) => {
            gsap.to(stump.position, {
                x: (idx - 1) * 0.8 + (Math.random() - 0.5) * 0.4,
                y: 1.8 + Math.random() * 0.5,
                z: 0.8 + Math.random() * 0.4,
                duration: 0.8,
                ease: 'power2.out'
            });
            gsap.to(stump.rotation, {
                x: Math.random() * 2,
                y: Math.random() * 2,
                z: Math.random() * 2,
                duration: 0.8
            });
        });

        this.bails.striker.forEach(bail => {
            gsap.to(bail.position, {
                x: (Math.random() - 0.5) * 1.5,
                y: 2.5 + Math.random() * 0.8,
                z: 1.2 + Math.random() * 0.8,
                duration: 0.9,
                ease: 'power2.out'
            });
            gsap.to(bail.rotation, {
                x: Math.random() * 4,
                y: Math.random() * 4,
                z: Math.random() * 4,
                duration: 0.9
            });
        });

        const outBanner = document.createElement('div');
        outBanner.className = 'wicket-out-banner';
        outBanner.style.position = 'absolute';
        outBanner.style.top = '50%';
        outBanner.style.left = '50%';
        outBanner.style.transform = 'translate(-50%, -50%) scale(0)';
        outBanner.style.background = 'rgba(220, 53, 69, 0.9)';
        outBanner.style.border = '3px solid #ffcbd0';
        outBanner.style.boxShadow = '0 0 30px rgba(220, 53, 69, 0.8)';
        outBanner.style.color = '#fff';
        outBanner.style.padding = '15px 40px';
        outBanner.style.borderRadius = '12px';
        outBanner.style.fontSize = '2.5rem';
        outBanner.style.fontWeight = '900';
        outBanner.style.letterSpacing = '0.15em';
        outBanner.style.zIndex = '999';
        outBanner.style.fontFamily = 'Impact, Charcoal, sans-serif';
        outBanner.textContent = 'OUT!';

        this.container.appendChild(outBanner);

        gsap.to(outBanner.style, {
            transform: 'translate(-50%, -50%) scale(1)',
            duration: 0.3,
            ease: 'back.out(1.7)'
        });

        gsap.to(outBanner, {
            opacity: 0,
            duration: 0.5,
            delay: 2.2,
            onComplete: () => {
                outBanner.remove();
            }
        });
    }

    resetWickets() {
        this.stumps.striker.forEach((stump, idx) => {
            stump.position.set([-0.15, 0, 0.15][idx], 0.4, 0);
            stump.rotation.set(0, 0, 0);
        });

        this.bails.striker.forEach((bail, idx) => {
            bail.position.set([-0.075, 0.075][idx], 0.815, 0);
            bail.rotation.set(0, 0, 0);
        });
    }

    triggerFielderChase(ballX, ballZ, runs) {
        let nearestFielderId = null;
        let minDist = Infinity;

        Object.keys(this.players).forEach(id => {
            if (id === 'striker' || id === 'nonStriker' || id === 'bowler' || id === 'keeper') return;
            const pos = this.players[id].position;
            const dist = Math.hypot(pos.x - ballX, pos.z - ballZ);
            if (dist < minDist) {
                minDist = dist;
                nearestFielderId = id;
            }
        });

        if (nearestFielderId) {
            const fielder = this.players[nearestFielderId];
            const originalPos = { x: fielder.position.x, z: fielder.position.z };

            const runDuration = Math.min(minDist * 0.05, 1.2);
            gsap.to(fielder.position, {
                x: ballX * 0.92,
                z: ballZ * 0.92,
                duration: runDuration,
                ease: 'power1.out',
                onComplete: () => {
                    gsap.to(this.ball.position, {
                        x: 0,
                        y: 1.0,
                        z: 14,
                        duration: 0.6,
                        ease: 'sine.out',
                        onComplete: () => {
                            this.ball.position.set(0, -5, 0);
                            gsap.to(fielder.position, {
                                x: originalPos.x,
                                z: originalPos.z,
                                duration: runDuration,
                                ease: 'power1.inOut'
                            });
                        }
                    });
                }
            });
        }
    }

    triggerBatsmenRuns(runs) {
        const striker = this.players['striker'];
        const nonStriker = this.players['nonStriker'];
        if (!striker || !nonStriker) return;

        const strikerX = striker.position.x;
        const nonStrikerX = nonStriker.position.x;

        const runCycle = (count) => {
            if (count <= 0) return;

            const strikerTargetZ = striker.position.z > 0 ? -10 : 10;
            const nonStrikerTargetZ = nonStriker.position.z > 0 ? -10 : 10;

            gsap.to(striker.position, {
                x: strikerX + 1.2,
                z: strikerTargetZ,
                duration: 1.1,
                ease: 'power1.inOut',
                onComplete: () => {
                    gsap.to(striker.position, { x: 0, duration: 0.2 });
                }
            });

            gsap.to(nonStriker.position, {
                x: nonStrikerX - 1.2,
                z: nonStrikerTargetZ,
                duration: 1.1,
                ease: 'power1.inOut',
                onComplete: () => {
                    gsap.to(nonStriker.position, { x: 0, duration: 0.2, onComplete: () => {
                        runCycle(count - 1);
                    }});
                }
            });
        };

        runCycle(runs <= 3 ? runs : 0);
    }

    onResize() {
        if (!this.container || !this.renderer) return;
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
    }

    animate() {
        requestAnimationFrame(this.animate.bind(this));

        if (this.controls) {
            this.controls.update();
        }

        this.updateLabels();

        this.renderer.render(this.scene, this.camera);
    }

    updateLabels() {
        const tempV = new THREE.Vector3();
        const widthHalf = (this.container.clientWidth || 400) / 2;
        const heightHalf = (this.container.clientHeight || 300) / 2;

        Object.keys(this.playerLabels).forEach(id => {
            const labelObj = this.playerLabels[id];
            
            // Project player using THREE world space coordinate projection
            labelObj.group.getWorldPosition(tempV);
            tempV.y += 2.0; // Place label above player head
            tempV.project(this.camera);

            if (tempV.z > 1) {
                labelObj.element.style.opacity = '0';
                return;
            }

            labelObj.element.style.opacity = '1';
            
            const x = (tempV.x * widthHalf) + widthHalf;
            const y = -(tempV.y * heightHalf) + heightHalf;

            labelObj.element.style.left = `${x}px`;
            labelObj.element.style.top = `${y}px`;
        });
    }
}
