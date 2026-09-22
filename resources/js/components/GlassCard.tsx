import React from 'react';

interface GlassCardProps extends React.HTMLAttributes<HTMLDivElement> {
    children: React.ReactNode;
    className?: string;
    variant?: 'default' | 'elevated' | 'interactive' | 'floating';
    hasGlow?: boolean;
}

export const GlassCard: React.FC<GlassCardProps> = ({
    children,
    className = '',
    variant = 'default',
    hasGlow = false,
    ...props
}) => {
    const baseStyles = 'relative rounded-2xl border transition-all duration-200';

    const variantStyles = {
        default: 'bg-white/[0.04] border-white/[0.08] backdrop-blur-md',
        elevated: 'bg-white/[0.06] border-white/[0.10] backdrop-blur-md shadow-xl shadow-black/40',
        interactive: 'bg-white/[0.04] border-white/[0.08] backdrop-blur-md hover:bg-white/[0.07] hover:border-white/[0.14] hover:shadow-lg hover:shadow-blue-500/5 cursor-pointer',
        floating: 'glass-modal border-white/[0.14]',
    };

    return (
        <div
            className={`${baseStyles} ${variantStyles[variant]} ${className}`}
            {...props}
        >
            {hasGlow && (
                <div
                    aria-hidden="true"
                    className="absolute -top-12 -right-12 w-36 h-36 bg-blue-500/10 rounded-full blur-2xl pointer-events-none"
                />
            )}
            {children}
        </div>
    );
};
