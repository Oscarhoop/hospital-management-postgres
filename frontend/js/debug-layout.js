// Layout Diagnostic Script
console.log('=== LAYOUT DIAGNOSTIC REPORT ===');

function diagnoseLayout() {
    // Check topbar
    const topbar = document.querySelector('.topbar');
    const currentPageTitle = document.getElementById('currentPageTitle');
    const userInfo = document.getElementById('userInfo');
    const pageTitles = document.querySelectorAll('.page-title');
    const sections = document.querySelectorAll('.section');
    const mainContent = document.querySelector('.main-content');
    const container = document.querySelector('.container');

    console.log('\n--- TOPBAR ANALYSIS ---');
    if (topbar) {
        const topbarStyles = window.getComputedStyle(topbar);
        console.log('Topbar element found:', topbar);
        console.log('Topbar computed styles:', {
            display: topbarStyles.display,
            flexDirection: topbarStyles.flexDirection,
            justifyContent: topbarStyles.justifyContent,
            alignItems: topbarStyles.alignItems,
            position: topbarStyles.position,
            width: topbarStyles.width,
            height: topbarStyles.height,
            zIndex: topbarStyles.zIndex,
            overflow: topbarStyles.overflow,
            gap: topbarStyles.gap
        });
        console.log('Topbar children:', topbar.children);
        console.log('Topbar direct children count:', topbar.children.length);
        
        Array.from(topbar.children).forEach((child, index) => {
            const childStyles = window.getComputedStyle(child);
            console.log(`Child ${index} (${child.tagName}#${child.id || 'no-id'}.${child.className}):`, {
                display: childStyles.display,
                flexGrow: childStyles.flexGrow,
                flexShrink: childStyles.flexShrink,
                marginLeft: childStyles.marginLeft,
                marginRight: childStyles.marginRight,
                position: childStyles.position,
                float: childStyles.float
            });
        });
    } else {
        console.error('❌ Topbar element NOT FOUND!');
    }

    console.log('\n--- CURRENT PAGE TITLE IN TOPBAR ---');
    if (currentPageTitle) {
        const titleStyles = window.getComputedStyle(currentPageTitle);
        console.log('currentPageTitle element:', currentPageTitle);
        console.log('currentPageTitle text:', currentPageTitle.textContent);
        console.log('currentPageTitle parent:', currentPageTitle.parentElement);
        console.log('currentPageTitle computed styles:', {
            display: titleStyles.display,
            position: titleStyles.position,
            flexGrow: titleStyles.flexGrow,
            flexShrink: titleStyles.flexShrink,
            marginLeft: titleStyles.marginLeft,
            marginRight: titleStyles.marginRight,
            float: titleStyles.float
        });
    } else {
        console.error('❌ currentPageTitle element NOT FOUND!');
    }

    console.log('\n--- USER INFO ---');
    if (userInfo) {
        const userInfoStyles = window.getComputedStyle(userInfo);
        console.log('userInfo element:', userInfo);
        console.log('userInfo display:', userInfoStyles.display);
        console.log('userInfo parent:', userInfo.parentElement);
        console.log('userInfo computed styles:', {
            display: userInfoStyles.display,
            flexGrow: userInfoStyles.flexGrow,
            flexShrink: userInfoStyles.flexShrink,
            marginLeft: userInfoStyles.marginLeft,
            marginRight: userInfoStyles.marginRight,
            position: userInfoStyles.position,
            float: userInfoStyles.float
        });
    } else {
        console.error('❌ userInfo element NOT FOUND!');
    }

    console.log('\n--- PAGE TITLES (in sections) ---');
    pageTitles.forEach((title, index) => {
        const titleStyles = window.getComputedStyle(title);
        const section = title.closest('.section');
        console.log(`Page Title ${index}:`, title.textContent);
        console.log(`  Parent section ID:`, section?.id || 'no section');
        console.log(`  Section display:`, section ? window.getComputedStyle(section).display : 'N/A');
        console.log(`  Title position:`, titleStyles.position);
        console.log(`  Title float:`, titleStyles.float);
        console.log(`  Title z-index:`, titleStyles.zIndex);
        
        // Check if title is somehow inside topbar
        if (topbar && topbar.contains(title)) {
            console.error(`WARNING: This page-title is INSIDE the topbar!`);
        }
    });

    console.log('\n--- MAIN CONTENT & CONTAINER ---');
    if (mainContent) {
        const mainStyles = window.getComputedStyle(mainContent);
        console.log('main-content:', {
            display: mainStyles.display,
            flexDirection: mainStyles.flexDirection,
            position: mainStyles.position
        });
    }
    
    if (container) {
        const containerStyles = window.getComputedStyle(container);
        console.log('container:', {
            display: containerStyles.display,
            position: containerStyles.position,
            marginTop: containerStyles.marginTop,
            paddingTop: containerStyles.paddingTop,
            zIndex: containerStyles.zIndex
        });
    }

    console.log('\n--- DOM STRUCTURE CHECK ---');
    if (topbar && mainContent) {
        console.log('Topbar is child of:', topbar.parentElement?.className);
        console.log('Main-content children:', Array.from(mainContent.children).map(c => c.tagName + '.' + c.className));
        
        // Check if topbar is properly placed
        const topbarIndex = Array.from(mainContent.children).indexOf(topbar);
        const containerIndex = Array.from(mainContent.children).findIndex(c => c.classList.contains('container'));
        console.log('Topbar index in main-content:', topbarIndex);
        console.log('Container index in main-content:', containerIndex);
        
        if (topbarIndex >= 0 && containerIndex >= 0 && topbarIndex < containerIndex) {
            console.log('Structure is correct: topbar comes before container');
        } else {
            console.error('Structure issue: topbar and container ordering is wrong!');
        }
    }

    console.log('\n--- POTENTIAL ISSUES DETECTED ---');
    const issues = [];
    
    // Check for absolute positioning
    document.querySelectorAll('.page-title, .page-header').forEach(el => {
        const styles = window.getComputedStyle(el);
        if (styles.position === 'absolute' || styles.position === 'fixed') {
            issues.push(`${el.className} has position: ${styles.position}`);
        }
        if (styles.float !== 'none') {
            issues.push(`${el.className} has float: ${styles.float}`);
        }
    });

    if (issues.length > 0) {
        console.error('Issues found:', issues);
    } else {
        console.log('No obvious positioning issues found');
    }

    console.log('\n=== END DIAGNOSTIC REPORT ===\n');
}

// Run on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', diagnoseLayout);
} else {
    diagnoseLayout();
}

// Also run after a short delay to catch dynamic changes
setTimeout(diagnoseLayout, 1000);

// Make it available globally
window.diagnoseLayout = diagnoseLayout;
console.log('You can run diagnoseLayout() anytime in the console');
