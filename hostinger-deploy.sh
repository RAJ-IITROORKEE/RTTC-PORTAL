#!/bin/bash

###############################################################################
# RTTC 2026 - Hostinger Deployment Script
# 
# Usage: bash hostinger-deploy.sh
#
# This script automates the one-time setup on Hostinger:
# 1. Creates persistent directories (symlinks)
# 2. Installs Composer dependencies
# 3. Sets up git SSH key
# 4. Creates .env file from template
###############################################################################

set -e  # Exit on error

echo "═══════════════════════════════════════════════════════════════"
echo "RTTC 2026 - Hostinger Deployment Setup"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Step 1: Create persistent directories
echo -e "${YELLOW}Step 1: Creating persistent directories...${NC}"
mkdir -p ../rttc-data/storage/uploads/documents
mkdir -p ../rttc-data/storage/uploads/notices
mkdir -p ../rttc-data/storage/logs
mkdir -p ../rttc-data/vendor
echo -e "${GREEN}✓ Directories created${NC}"
echo ""

# Step 2: Create symlinks
echo -e "${YELLOW}Step 2: Creating symlinks...${NC}"
if [ -d "storage" ]; then
    echo "  storage/ already exists"
else
    ln -s ../../rttc-data/storage storage
    echo -e "${GREEN}✓ storage/ symlinked${NC}"
fi

if [ -d "vendor" ]; then
    echo "  vendor/ already exists"
else
    ln -s ../../rttc-data/vendor vendor
    echo -e "${GREEN}✓ vendor/ symlinked${NC}"
fi
echo ""

# Step 3: Install Composer dependencies
echo -e "${YELLOW}Step 3: Installing Composer dependencies...${NC}"
if command -v composer &> /dev/null; then
    composer install --no-dev --optimize-autoloader
    echo -e "${GREEN}✓ Composer dependencies installed${NC}"
else
    echo -e "${RED}✗ Composer not found. Install manually:${NC}"
    echo "  1. Download: curl -sS https://getcomposer.org/installer | php"
    echo "  2. Then run: composer install --no-dev"
fi
echo ""

# Step 4: Create .env if it doesn't exist
echo -e "${YELLOW}Step 4: Setting up .env file...${NC}"
if [ -f ".env" ]; then
    echo -e "${YELLOW}  .env already exists. Skipping...${NC}"
else
    cp .env.example .env
    echo -e "${GREEN}✓ .env created from template${NC}"
    echo -e "${YELLOW}  TODO: Edit .env with your Hostinger credentials:${NC}"
    echo "  nano .env"
fi
echo ""

# Step 5: Verify .env is in .gitignore
echo -e "${YELLOW}Step 5: Verifying .gitignore...${NC}"
if grep -q "^\.env$" .gitignore; then
    echo -e "${GREEN}✓ .env is in .gitignore${NC}"
else
    echo ".env" >> .gitignore
    echo -e "${GREEN}✓ Added .env to .gitignore${NC}"
fi
echo ""

# Step 6: SSH Key setup instructions
echo -e "${YELLOW}Step 6: SSH Key Setup (Manual)${NC}"
echo -e "${YELLOW}Generate SSH key:${NC}"
echo "  ssh-keygen -t ed25519 -f ~/.ssh/rttc-deploy -N \"\""
echo ""
echo -e "${YELLOW}Add to GitHub Deploy Keys:${NC}"
echo "  1. Go to: https://github.com/RAJ-IITROORKEE/RTTC-PORTAL/settings/keys"
echo "  2. Add Deploy Key:"
echo "     cat ~/.ssh/rttc-deploy.pub"
echo "  3. Enable 'Allow write access'"
echo ""
echo -e "${YELLOW}Configure git to use SSH key:${NC}"
echo "  git config core.sshCommand \"ssh -i ~/.ssh/rttc-deploy\""
echo "  git remote set-url origin git@github.com:RAJ-IITROORKEE/RTTC-PORTAL.git"
echo ""

# Step 7: Permissions
echo -e "${YELLOW}Step 7: Setting correct permissions...${NC}"
chmod -R 755 .
chmod -R 755 storage/
chmod 644 .env
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# Step 8: Display next steps
echo "═══════════════════════════════════════════════════════════════"
echo -e "${GREEN}✓ SETUP COMPLETE!${NC}"
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo -e "${YELLOW}NEXT STEPS:${NC}"
echo ""
echo "1. Edit .env with your Hostinger credentials:"
echo "   nano .env"
echo ""
echo "2. Update these fields:"
echo "   - APP_URL: https://your-domain.com/rttc-portal/"
echo "   - DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME"
echo "   - SMTP_USERNAME, SMTP_PASSWORD (if using email)"
echo "   - RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET"
echo ""
echo "3. Setup SSH Key for git:"
echo "   ssh-keygen -t ed25519 -f ~/.ssh/rttc-deploy -N \"\""
echo "   cat ~/.ssh/rttc-deploy.pub  # Copy to GitHub Deploy Keys"
echo ""
echo "4. Configure git:"
echo "   git config core.sshCommand \"ssh -i ~/.ssh/rttc-deploy\""
echo "   git remote set-url origin git@github.com:RAJ-IITROORKEE/RTTC-PORTAL.git"
echo ""
echo "5. Import database:"
echo "   mysql -u username -p database_name < database/final_updated.sql"
echo ""
echo "6. Test deployment:"
echo "   git pull"
echo ""
echo "═══════════════════════════════════════════════════════════════"
