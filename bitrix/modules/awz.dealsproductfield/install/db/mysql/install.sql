CREATE TABLE IF NOT EXISTS `b_awz_gitmd` (
    ID int(18) NOT NULL AUTO_INCREMENT,
    LINK varchar(256) NOT NULL,
    HASH varchar(64) NOT NULL,
    CREATE_DATE datetime NOT NULL,
    EXPIRED_DATE datetime NOT NULL,
    CONTENT longtext,
    PRIMARY KEY (`ID`),
    index IX_HASH (HASH)
);